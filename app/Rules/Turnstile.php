<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile check for the public registration form (FR-033, research R17).
 * Without a secret it passes outside production and fails closed in production.
 */
class Turnstile implements ValidationRule
{
    public const MESSAGE = 'Não foi possível confirmar que não é um robô. Tente novamente.';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ?string $remoteIp = null) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.turnstile.secret_key')) && filled(config('services.turnstile.site_key'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isConfigured()) {
            if (app()->isProduction()) {
                Log::error('Turnstile is not configured; refusing registration.');
                $fail(self::MESSAGE);
            }

            return;
        }

        if (! is_string($value) || $value === '') {
            $fail(self::MESSAGE);

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => $this->remoteIp,
            ]);

            if (! $response->json('success')) {
                $fail(self::MESSAGE);
            }
        } catch (Throwable $exception) {
            Log::warning('Turnstile verification failed', ['error' => $exception->getMessage()]);
            $fail(self::MESSAGE);
        }
    }
}
