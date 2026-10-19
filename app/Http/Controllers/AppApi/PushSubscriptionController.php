<?php

namespace App\Http\Controllers\AppApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use NotificationChannels\WebPush\PushSubscription;

/**
 * This device's Web Push subscription (contracts/app-api.md, R12). One row per device;
 * subscribing again from the same browser updates its keys.
 */
class PushSubscriptionController extends Controller
{
    /**
     * The server POSTs to whatever endpoint is stored, so only real browser push
     * services are accepted (no requests to internal or arbitrary hosts).
     */
    private const PUSH_SERVICE_HOSTS = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
        '*.push.apple.com',
        '*.notify.windows.com',
    ];

    public function store(Request $request, #[CurrentUser] User $user): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH, function (string $attribute, string $value, \Closure $fail): void {
                if (! Str::is(self::PUSH_SERVICE_HOSTS, (string) parse_url($value, PHP_URL_HOST))) {
                    $fail('Serviço de notificações não suportado.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);

        $subscription = $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm',
        );

        $subscription->forceFill(['device' => $this->deviceLabel((string) $request->userAgent())])->save();

        return response()->noContent();
    }

    public function destroy(Request $request, #[CurrentUser] User $user): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
        ]);

        $user->deletePushSubscription($validated['endpoint']);

        return response()->noContent();
    }

    /**
     * A coarse label for the preferences page; the full user agent is never stored.
     */
    private function deviceLabel(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, ['iPhone', 'iPod']) => 'iPhone',
            Str::contains($userAgent, 'iPad') => 'iPad',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, 'Macintosh') => 'Mac',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => 'Outro dispositivo',
        };
    }
}
