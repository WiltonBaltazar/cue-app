<?php

use App\Models\User;

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
                'twoFactorEnabled' => false,
            ])
            ->where('unreadNotifications', 0));
});
