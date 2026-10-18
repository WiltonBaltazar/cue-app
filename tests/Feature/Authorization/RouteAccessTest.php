<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Driven by the route list, so every route added later is covered automatically:
| - guests never reach a route that requires authentication;
| - everything under /gestao requires the coordinator role;
| - volunteers get 403 on every coordinator-only route.
*/

/**
 * @return Collection<int, RoutingRoute>
 */
function appRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RoutingRoute $route) => Str::startsWith($route->uri(), ['_', 'storage/', 'up']));
}

function requiresAuth(RoutingRoute $route): bool
{
    return collect($route->gatherMiddleware())
        ->contains(fn ($middleware) => is_string($middleware)
            && ($middleware === 'auth' || Str::startsWith($middleware, 'auth:')));
}

function isCoordinatorOnly(RoutingRoute $route): bool
{
    return in_array('role:coordinator', $route->gatherMiddleware(), true);
}

function callableUri(RoutingRoute $route): string
{
    return '/'.ltrim((string) preg_replace(['/\{[^}]+\?\}/', '/\{[^}]+\}/'], ['', '1'], $route->uri()), '/');
}

function routeMethod(RoutingRoute $route): string
{
    return collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->first();
}

test('guests are turned away from every route that requires authentication', function () {
    $protected = appRoutes()->filter(fn (RoutingRoute $route) => requiresAuth($route));

    expect($protected)->not->toBeEmpty();

    foreach ($protected as $route) {
        $uri = callableUri($route);
        $response = $this->call(routeMethod($route), $uri, [], [], [], ['HTTP_ACCEPT' => Str::startsWith($uri, '/app-api') ? 'application/json' : 'text/html']);

        if (Str::startsWith($uri, '/app-api')) {
            expect($response->getStatusCode())->toBe(401, "{$uri} should return 401 to guests");
        } else {
            expect($response->isRedirect(route('login')))->toBeTrue("{$uri} should redirect guests to login");
        }
    }
});

test('every management route requires the coordinator role', function () {
    $management = appRoutes()->filter(fn (RoutingRoute $route) => Str::startsWith($route->uri(), 'gestao'));

    expect($management)->not->toBeEmpty();

    foreach ($management as $route) {
        expect(isCoordinatorOnly($route))->toBeTrue("/{$route->uri()} must use role:coordinator");
    }
});

test('volunteers get 403 on every coordinator-only route', function () {
    $volunteer = User::factory()->volunteer()->create();

    foreach (appRoutes()->filter(fn (RoutingRoute $route) => isCoordinatorOnly($route)) as $route) {
        $uri = callableUri($route);

        $this->actingAs($volunteer)
            ->call(routeMethod($route), $uri)
            ->assertForbidden();
    }
});

test('coordinators without confirmed two-factor are sent to set it up', function () {
    $coordinator = User::factory()->volunteer()->create();
    $coordinator->forceFill(['role' => 'coordinator'])->save();

    $this->actingAs($coordinator)
        ->get(route('coord.dashboard'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs($coordinator)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
});

test('coordinators with confirmed two-factor reach the management area', function () {
    $this->actingAs(User::factory()->coordinator()->create())
        ->get(route('coord.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('coord/dashboard'));
});
