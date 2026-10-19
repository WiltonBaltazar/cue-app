<?php

use App\Enums\EventStatus;
use App\Enums\ScheduleStatus;
use App\Models\Assignment;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Push\PushChannel;
use App\Notifications\Push\PushDeliveryFailed;
use App\Notifications\SchedulePublished;
use App\Notifications\ShiftCheck;
use App\Notifications\ShiftReminder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Support\ScheduleScenario;

/**
 * Replaces the real push client: records every queued push and answers each with
 * the HTTP status configured for its endpoint (201 by default).
 */
function fakePushService(array $statuses = []): ArrayObject
{
    $sent = new ArrayObject;
    $pending = [];

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->andReturnUsing(
        function (Subscription $subscription, string $payload, array $options) use ($sent, &$pending): void {
            $pending[] = $subscription->getEndpoint();
            $sent->append(['endpoint' => $subscription->getEndpoint(), 'payload' => json_decode($payload, true), 'options' => $options]);
        },
    );
    $webPush->shouldReceive('flush')->andReturnUsing(function () use (&$pending, $statuses): Generator {
        $endpoints = $pending;
        $pending = [];

        foreach ($endpoints as $endpoint) {
            $status = $statuses[$endpoint] ?? 201;

            yield new MessageSentReport(new Request('POST', $endpoint), new Response($status), $status < 300, (string) $status);
        }
    });

    app()->when(WebPushChannel::class)->needs(WebPush::class)->give(fn () => $webPush);

    return $sent;
}

function subscribeDevice(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/device-1'): PushSubscription
{
    return $user->updatePushSubscription($endpoint, 'BPubKey', 'authToken', 'aes128gcm');
}

/**
 * A confirmed shift in a published schedule; the event's arrival is next Sunday 07:30.
 */
function publishedShift(): Assignment
{
    $scenario = ScheduleScenario::create();
    $scenario->schedule->forceFill(['status' => ScheduleStatus::Published])->save();

    return Assignment::factory()->create([
        'schedule_id' => $scenario->schedule->id,
        'position_id' => $scenario->position->id,
        'volunteer_id' => $scenario->volunteer()->id,
    ]);
}

test('a notification is pushed with only title, short body, in-app url, tag and type', function () {
    $sent = fakePushService();
    $user = User::factory()->create();
    subscribeDevice($user);

    $user->notify(new SchedulePublished(3, str_repeat('Dom 2/11 · Culto de Domingo · Operação de câmara · ', 5)));

    expect($sent)->toHaveCount(1);
    $payload = $sent[0]['payload'];
    expect(array_keys($payload))->toBe(['title', 'body', 'url', 'tag', 'type'])
        ->and($payload['type'])->toBe('schedule_published')
        ->and($payload['url'])->toBe('/minha-escala')
        ->and(mb_strlen($payload['body']))->toBeLessThanOrEqual(120)
        ->and($sent[0]['options'])->toBe(['TTL' => 86400, 'urgency' => 'normal']);

    // The in-app copy is always kept.
    expect($user->notifications()->count())->toBe(1);
});

test('push goes to every device of the user', function () {
    $sent = fakePushService();
    $user = User::factory()->create();
    subscribeDevice($user, 'https://fcm.googleapis.com/fcm/send/phone');
    subscribeDevice($user, 'https://web.push.apple.com/tablet');

    $user->notify(new SchedulePublished(1, 'Dom 2/11 · Culto'));

    expect(collect($sent)->pluck('endpoint')->sort()->values()->all())
        ->toBe(['https://fcm.googleapis.com/fcm/send/phone', 'https://web.push.apple.com/tablet']);
});

test('a type switched off in preferences is not pushed but still stored in-app', function () {
    $sent = fakePushService();
    $user = User::factory()->create();
    subscribeDevice($user);
    NotificationPreference::query()->create(['user_id' => $user->id, 'type' => 'schedule_published', 'push' => false]);

    $user->notify(new SchedulePublished(1, 'Dom 2/11 · Culto'));

    expect($sent)->toHaveCount(0)
        ->and($user->notifications()->count())->toBe(1);
});

test('users without a subscribed device only get the in-app notification', function () {
    $sent = fakePushService();
    $user = User::factory()->create();

    $user->notify(new SchedulePublished(1, 'Dom 2/11 · Culto'));

    expect($sent)->toHaveCount(0)
        ->and($user->notifications()->count())->toBe(1);
});

test('subscriptions the push service reports as gone (404/410) are deleted', function () {
    fakePushService([
        'https://fcm.googleapis.com/fcm/send/gone' => 410,
        'https://fcm.googleapis.com/fcm/send/unknown' => 404,
    ]);
    $user = User::factory()->create();
    subscribeDevice($user, 'https://fcm.googleapis.com/fcm/send/gone');
    subscribeDevice($user, 'https://fcm.googleapis.com/fcm/send/unknown');
    subscribeDevice($user, 'https://fcm.googleapis.com/fcm/send/alive');

    $user->notify(new SchedulePublished(1, 'Dom 2/11 · Culto'));

    expect($user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/alive'])
        ->and($user->notifications()->count())->toBe(1);
});

test('other push failures are thrown so the queued notification is retried with backoff', function () {
    fakePushService(['https://fcm.googleapis.com/fcm/send/device-1' => 500]);
    $user = User::factory()->create();
    subscribeDevice($user);
    $notification = new SchedulePublished(1, 'Dom 2/11 · Culto');

    expect(fn () => app(PushChannel::class)->send($user, $notification))->toThrow(PushDeliveryFailed::class)
        ->and($notification->tries)->toBe(3)
        ->and($notification->backoff())->toBe([30, 120, 600])
        ->and($user->pushSubscriptions()->count())->toBe(1);
});

test('reminder payloads carry no personal data', function () {
    $sent = fakePushService();
    $assignment = publishedShift();
    $user = $assignment->volunteer->user;
    $user->forceFill(['email' => 'ana@example.org'])->save();
    subscribeDevice($user);
    $this->travelTo($assignment->event->arrival_at->subHours(23));

    $this->artisan('shifts:remind')->assertSuccessful();

    $encoded = json_encode($sent[0]['payload'], JSON_UNESCAPED_UNICODE);
    expect($encoded)
        ->not->toContain($user->phone)
        ->not->toContain('ana@example.org')
        ->not->toContain($assignment->volunteer->full_name)
        ->not->toContain($assignment->volunteer->birth_date)
        ->toContain('chegada 07:30');
});

test('the 72 h check is sent once, with a "Não posso ir" action, and lasts until the event starts', function () {
    $sent = fakePushService();
    $assignment = publishedShift();
    subscribeDevice($assignment->volunteer->user);
    $this->travelTo($assignment->event->arrival_at->subHours(70));

    $this->artisan('shifts:remind')->assertSuccessful();
    $this->artisan('shifts:remind')->assertSuccessful();

    expect($sent)->toHaveCount(1);
    $payload = $sent[0]['payload'];
    expect($payload['type'])->toBe('shift_check')
        ->and($payload['tag'])->toBe("assignment-{$assignment->id}")
        ->and($payload['actions'])->toBe([[
            'action' => 'decline',
            'title' => 'Não posso ir',
            'url' => "/minha-escala?recusar={$assignment->id}",
        ]])
        ->and($sent[0]['options']['TTL'])->toBe((int) now()->diffInSeconds($assignment->event->starts_at));

    // Lock version and audit log are untouched by the bookkeeping.
    expect($assignment->fresh()->lock_version)->toBe($assignment->lock_version)
        ->and($assignment->fresh()->check_sent_at)->not->toBeNull();
});

test('the 24 h reminder is sent once, after the check', function () {
    Notification::fake();
    $assignment = publishedShift();
    $user = $assignment->volunteer->user;

    $this->travelTo($assignment->event->arrival_at->subHours(71));
    $this->artisan('shifts:remind');
    $this->travelTo($assignment->event->arrival_at->subHours(23));
    $this->artisan('shifts:remind');
    $this->artisan('shifts:remind');

    Notification::assertSentToTimes($user, ShiftCheck::class, 1);
    Notification::assertSentToTimes($user, ShiftReminder::class, 1);
});

test('a shift booked inside the last 24 h gets only the reminder', function () {
    Notification::fake();
    $assignment = publishedShift();
    $this->travelTo($assignment->event->arrival_at->subHours(10));

    $this->artisan('shifts:remind');

    Notification::assertSentToTimes($assignment->volunteer->user, ShiftReminder::class, 1);
    Notification::assertNotSentTo($assignment->volunteer->user, ShiftCheck::class);
});

test('no reminders for draft schedules, cancelled events or shifts not confirmed', function () {
    Notification::fake();
    $draft = publishedShift();
    $draft->schedule->forceFill(['status' => ScheduleStatus::Draft])->save();
    $this->travelTo($draft->event->arrival_at->subHours(23));
    $this->artisan('shifts:remind');

    $draft->schedule->forceFill(['status' => ScheduleStatus::Published])->save();
    $draft->event->forceFill(['status' => EventStatus::Cancelled])->save();
    $this->artisan('shifts:remind');

    Notification::assertNothingSent();
});

test('a new person in the place gets their own reminders', function () {
    Notification::fake();
    $assignment = publishedShift();
    $this->travelTo($assignment->event->arrival_at->subHours(23));
    $this->artisan('shifts:remind');

    $other = ScheduleScenario::create()->volunteer();
    $assignment->volunteer_id = $other->id;
    $assignment->save();
    $this->artisan('shifts:remind');

    Notification::assertSentToTimes($other->user, ShiftReminder::class, 1);
});

test('a device subscribes, updates its keys and unsubscribes', function () {
    $user = User::factory()->create();
    $body = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => 'BKey', 'auth' => 'secret'],
        'contentEncoding' => 'aes128gcm',
    ];

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
        ->postJson(route('app-api.push-subscriptions.store'), $body)
        ->assertNoContent();
    $this->actingAs($user)
        ->postJson(route('app-api.push-subscriptions.store'), [...$body, 'keys' => ['p256dh' => 'BNew', 'auth' => 'secret2']])
        ->assertNoContent();

    $subscription = $user->pushSubscriptions()->sole();
    expect($subscription->public_key)->toBe('BNew');

    $this->actingAs($user)
        ->deleteJson(route('app-api.push-subscriptions.destroy'), ['endpoint' => $body['endpoint']])
        ->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('only real browser push services are accepted as endpoints', function (string $endpoint) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('app-api.push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BKey', 'auth' => 'secret'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
})->with([
    'plain http' => 'http://fcm.googleapis.com/fcm/send/abc',
    'internal host' => 'https://127.0.0.1/push',
    'arbitrary host' => 'https://evil.example.com/push',
]);

test("a user cannot remove another user's device", function () {
    $owner = User::factory()->create();
    subscribeDevice($owner);

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('app-api.push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1'])
        ->assertNoContent();

    expect($owner->pushSubscriptions()->count())->toBe(1);
});

test('volunteers choose which types are pushed, among the ones meant for them', function () {
    $volunteer = publishedShift()->volunteer->user;

    $this->actingAs($volunteer)
        ->get(route('notification-preferences.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/profile/notifications')
            ->where('preferences', fn ($preferences) => collect($preferences)->pluck('type')->doesntContain('registration_submitted'))
            ->where('preferences.0.push', true));

    $this->actingAs($volunteer)
        ->put(route('notification-preferences.update'), ['preferences' => ['shift_reminder' => false]])
        ->assertRedirect();

    expect($volunteer->wantsPush('shift_reminder'))->toBeFalse()
        ->and($volunteer->wantsPush('shift_check'))->toBeTrue();

    $this->actingAs($volunteer)
        ->put(route('notification-preferences.update'), ['preferences' => ['registration_submitted' => false]])
        ->assertSessionHasErrors('preferences');
});
