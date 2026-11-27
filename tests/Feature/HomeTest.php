<?php

use App\Models\User;
use App\Models\Volunteer;
use Spatie\Activitylog\Models\Activity;

test('guests are sent to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('volunteers see the volunteer home', function () {
    $this->actingAs(User::factory()->volunteer()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/home')
            ->where('auth.user.role', 'volunteer'));
});

test('coordinators start in the management area', function () {
    $this->actingAs(User::factory()->coordinator()->create())
        ->get(route('home'))
        ->assertRedirect(route('coord.dashboard'));
});

test('shared props expose only what the UI needs about the user', function () {
    $user = User::factory()->volunteer()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => 'volunteer',
                'volunteerStatus' => null,
                'volunteerView' => null,
                'twoFactorEnabled' => false,
            ])
            ->where('unreadNotifications', 0));
});

test('a coordinator can view the app as a volunteer, read-only, until they open management', function () {
    $coordinator = User::factory()->coordinator()->create();
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Ana Machava']);
    $this->actingAs($coordinator);

    $this->post(route('coord.volunteers.view-as', $volunteer))->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/home')
            ->where('auth.user.volunteerView', ['id' => $volunteer->id, 'name' => 'Ana Machava']));

    // Their pages show their data.
    $this->get(route('absences.index'))->assertOk();
    $this->get(route('volunteer.profile.edit'))
        ->assertInertia(fn ($page) => $page->where('volunteer.id', $volunteer->id));

    // Nothing can be changed on their behalf.
    $this->post(route('profile.pause'))->assertRedirect();
    expect($volunteer->fresh()->status->value)->toBe('active');

    expect(Activity::query()->where('event', 'viewed_as_volunteer')->where('subject_id', $volunteer->id)->exists())->toBeTrue();

    $this->get(route('coord.dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.user.volunteerView', null));

    $this->get(route('home'))->assertRedirect(route('coord.dashboard'));
});

test('a coordinator who serves can view their own app without naming a volunteer', function () {
    $coordinator = User::factory()->coordinator()->create();
    $own = Volunteer::factory()->active()->for($coordinator)->create();

    $this->actingAs($coordinator->fresh())->post(route('volunteer-view.enter'))->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('auth.user.volunteerView.id', $own->id));
    expect(Activity::query()->where('event', 'viewed_as_volunteer')->exists())->toBeFalse();
});

test('volunteers can not view the app as someone else', function () {
    $other = Volunteer::factory()->active()->create();

    $this->actingAs(User::factory()->volunteer()->create())
        ->post(route('coord.volunteers.view-as', $other))
        ->assertForbidden();
});
