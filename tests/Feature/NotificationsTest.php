<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\ExampleNotification;

function testNotification(string $title = 'Nova escala publicada'): ExampleNotification
{
    return new ExampleNotification($title);
}

test('notifications are stored in-app with type, title, body and url only', function () {
    $user = User::factory()->create();

    $user->notify(testNotification());

    expect($user->notifications()->first()?->data)->toBe([
        'type' => 'schedule_published',
        'title' => 'Nova escala publicada',
        'body' => 'Dom 2 Nov · Culto de Domingo · Áudio · chegada 07:30',
        'url' => '/minha-escala',
    ]);
});

test('notifications are only queued when the surrounding transaction commits', function () {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user) {
            $user->notify(testNotification());

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        //
    }

    expect(DB::table('jobs')->count())->toBe(0);

    DB::transaction(fn () => $user->notify(testNotification()));

    expect(DB::table('jobs')->count())->toBe(1);
});

test('the notification centre lists, counts and marks only your own notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(testNotification('Primeira'));
    $other->notify(testNotification('De outra pessoa'));

    $this->actingAs($user)
        ->getJson(route('app-api.notifications.unread-count'))
        ->assertOk()
        ->assertExactJson(['count' => 1]);

    $list = $this->actingAs($user)
        ->getJson(route('app-api.notifications.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Primeira')
        ->assertJsonPath('nextPage', null);

    $this->actingAs($user)
        ->postJson(route('app-api.notifications.read', $list->json('data.0.id')))
        ->assertNoContent();

    expect($user->unreadNotifications()->count())->toBe(0);

    $othersId = $other->notifications()->value('id');
    $this->actingAs($user)
        ->postJson(route('app-api.notifications.read', $othersId))
        ->assertNotFound();
});

test('in-app notifications older than 90 days are pruned daily', function () {
    $user = User::factory()->create();
    $user->notify(testNotification('Antiga'));
    $user->notify(testNotification('Recente'));
    DatabaseNotification::query()->where('data->title', 'Antiga')->update(['created_at' => now()->subDays(91)]);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'prune-notifications');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');

    $event->run(app());

    expect($user->notifications()->pluck('data')->pluck('title')->all())->toBe(['Recente']);
});

test('push is on by default and can be switched off per type', function () {
    $user = User::factory()->create();

    expect($user->wantsPush('schedule_published'))->toBeTrue();

    $user->notificationPreferences()->create(['type' => 'schedule_published', 'push' => false]);

    expect($user->wantsPush('schedule_published'))->toBeFalse()
        ->and($user->wantsPush('shift_reminder'))->toBeTrue();
});

test('notification ids are UUIDs', function () {
    $user = User::factory()->create();
    $user->notify(testNotification());

    expect(Str::isUuid((string) $user->notifications()->value('id')))->toBeTrue();
});
