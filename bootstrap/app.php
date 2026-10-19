<?php

use App\Http\Middleware\EnsureConsentCurrent;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureVolunteerActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['api', 'auth:sanctum', 'throttle:app-api'])
                ->prefix('app-api')
                ->name('app-api.')
                ->group(base_path('routes/app-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Behind the reverse proxy (Coolify/Traefik), which terminates TLS: trust its
        // X-Forwarded-* headers so URLs, secure cookies and HSTS see HTTPS. The app
        // container is only reachable through that proxy.
        $middleware->trustProxies(at: '*');

        // Check the role before route-model binding, so a volunteer gets 403 rather than
        // learning which IDs exist from 404s.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRole::class);

        $middleware->alias([
            'role' => EnsureRole::class,
            'volunteer.active' => EnsureVolunteerActive::class,
        ]);

        // Session + CSRF for same-origin /app-api requests (Sanctum SPA mode).
        $middleware->statefulApi();

        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureConsentCurrent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'app-api/*') || $request->expectsJson(),
        );

        // Portuguese error pages for Inertia/HTML requests (contracts/routes.md).
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'A sessão expirou, tente novamente.']);

                return back();
            }

            $showServerErrors = ! app()->hasDebugModeEnabled() && in_array($status, [500, 503], true);

            if (in_array($status, [403, 404, 429], true) || $showServerErrors) {
                return Inertia::render('errors/show', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
