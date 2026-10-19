<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Which notifications arrive as push on this user's devices (FR-025). In-app copies are
 * always kept (FR-040). Coordinators who also volunteer see both sets.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $stored = $user->notificationPreferences()->pluck('push', 'type');

        return Inertia::render('volunteer/profile/notifications', [
            'preferences' => collect($this->typesFor($user))
                ->map(fn (array $type, string $key): array => [
                    'type' => $key,
                    'label' => $type['label'],
                    'description' => $type['description'],
                    'push' => (bool) ($stored[$key] ?? true),
                ])
                ->values(),
            'devices' => $user->pushSubscriptions()
                ->latest()
                ->get()
                ->map(fn (PushSubscription $subscription): array => [
                    'id' => $subscription->id,
                    'label' => $subscription->getAttribute('device') ?? 'Dispositivo',
                    'since' => $subscription->created_at?->toDateString(),
                ]),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $types = array_keys($this->typesFor($user));

        $validated = $request->validate([
            'preferences' => ['required', 'array:'.implode(',', $types)],
            'preferences.*' => ['boolean'],
        ]);

        foreach ($validated['preferences'] as $type => $push) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['push' => (bool) $push],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferências de notificação guardadas.']);

        return back();
    }

    /**
     * @return array<string, array{label: string, description: string, for: string}>
     */
    private function typesFor(User $user): array
    {
        $audiences = array_filter([
            'both',
            $user->volunteer !== null ? 'volunteer' : null,
            $user->isCoordinator() ? 'coordinator' : null,
        ]);

        return array_filter(NotificationPreference::TYPES, fn (array $type): bool => in_array($type['for'], $audiences, true));
    }
}
