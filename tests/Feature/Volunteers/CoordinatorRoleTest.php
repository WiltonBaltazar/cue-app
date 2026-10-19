<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create();
    $this->scenario = ScheduleScenario::create();
    $this->volunteer = $this->scenario->volunteer(attributes: ['full_name' => 'Ana Machava']);
});

test('a coordinator makes a volunteer coordinator, confirming with their password', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.coordinators.store', $this->volunteer->user), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($this->volunteer->user->fresh()->role)->toBe(UserRole::Coordinator)
        ->and(Activity::query()->where('subject_id', $this->volunteer->user->id)->where('subject_type', User::class)->latest('id')->first()->properties['attributes']['role'] ?? null)->toBe('coordinator');

    // Still a volunteer profile, so still on schedules.
    $this->actingAs($this->coordinator)->post(route('coord.schedules.generate', $this->scenario->schedule));
    expect($this->volunteer->assignments()->count())->toBe(1);
});

test('the role change needs the acting coordinator\'s own password', function () {
    $this->actingAs($this->coordinator)
        ->post(route('coord.coordinators.store', $this->volunteer->user), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect($this->volunteer->user->fresh()->role)->toBe(UserRole::Volunteer);
});

test('a coordinator takes the role away, but never from the last one', function () {
    $this->volunteer->user->forceFill(['role' => UserRole::Coordinator])->save();

    $this->actingAs($this->coordinator)
        ->delete(route('coord.coordinators.destroy', $this->volunteer->user), ['password' => 'password'])
        ->assertSessionHasNoErrors();
    expect($this->volunteer->user->fresh()->role)->toBe(UserRole::Volunteer);

    $this->actingAs($this->coordinator)
        ->delete(route('coord.coordinators.destroy', $this->coordinator), ['password' => 'password'])
        ->assertSessionHasErrors('role');
    expect($this->coordinator->fresh()->role)->toBe(UserRole::Coordinator);
});

test('registrations not yet approved can not be made coordinator', function () {
    $pending = Volunteer::factory()->create();

    $this->actingAs($this->coordinator)
        ->post(route('coord.coordinators.store', $pending->user), ['password' => 'password'])
        ->assertSessionHasErrors('role');

    expect($pending->user->fresh()->role)->toBe(UserRole::Volunteer);
});

test('volunteers can not change roles', function () {
    $this->actingAs($this->volunteer->user)
        ->post(route('coord.coordinators.store', $this->volunteer->user), ['password' => 'password'])
        ->assertForbidden();
});

test('settings list the coordinators and the volunteer page offers the role', function () {
    $this->actingAs($this->coordinator)
        ->get(route('coord.settings.edit'))
        ->assertInertia(fn ($page) => $page->has('coordinators', 1)->where('coordinators.0.isMe', true));

    $this->actingAs($this->coordinator)
        ->get(route('coord.volunteers.edit', $this->volunteer))
        ->assertInertia(fn ($page) => $page->where('account.isCoordinator', false)->where('account.userId', $this->volunteer->user->id));
});
