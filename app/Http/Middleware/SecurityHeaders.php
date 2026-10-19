<?php

namespace App\Http\Middleware;

use App\Rules\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections (constitution II, research R18).
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $devServer = $this->viteDevServer();
        $devSocket = $devServer === null ? null : preg_replace('/^http/', 'ws', $devServer);
        // Cloudflare Turnstile on the registration form (FR-033), only when configured.
        $turnstile = Turnstile::isConfigured() ? 'https://challenges.cloudflare.com' : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $devServer, $turnstile],
            // Radix/React set inline style attributes; inline <style> carries no script risk.
            'style-src' => ["'self'", "'unsafe-inline'", $devServer],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:', $devServer],
            'connect-src' => ["'self'", $devServer, $devSocket, $turnstile],
            'frame-src' => [$turnstile ?? "'none'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    /**
     * Origin of the local Vite dev server while `npm run dev` is running, so HMR keeps working.
     */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(public_path('hot')));

        return $url === '' ? null : rtrim($url, '/');
    }
}
