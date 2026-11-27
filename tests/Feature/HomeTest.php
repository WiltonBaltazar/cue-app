<?php

use App\Models\User;
use App\Models\Volunteer;

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
                'volunteerView' => false,
                'twoFactorEnabled' => false,
            ])
            ->where('unreadNotifications', 0));
});

test('a coordinator who serves can switch to the volunteer view and back', function () {
    $coordinator = User::factory()->coordinator()->create();
    Volunteer::factory()->active()->for($coordinator)->create();
    $this->actingAs($coordinator->fresh());

    $this->post(route('volunteer-view.enter'))->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/home')
            ->where('auth.user.volunteerView', true));

    $this->get(route('coord.dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.user.volunteerView', false));

    $this->get(route('home'))->assertRedirect(route('coord.dashboard'));
});

test('a coordinator without a serving profile is sent to create one', function () {
    $this->actingAs(User::factory()->coordinator()->create())
        ->post(route('volunteer-view.enter'))
        ->assertRedirect(route('coord.serving-profile.create'));

    $this->get(route('home'))->assertRedirect(route('coord.dashboard'));
});

test('volunteers can not use the volunteer view switch', function () {
    $this->actingAs(User::factory()->volunteer()->create())
        ->post(route('volunteer-view.enter'))
        ->assertForbidden();
});
