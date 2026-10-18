<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered in Portuguese', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));

    expect(route('login', absolute: false))->toBe('/entrar');
});

test('users can log in with their phone number in any common format', function (string $typed) {
    $user = User::factory()->create(['phone' => '+258841234567']);

    $this->post(route('login.store'), [
        'phone' => $typed,
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
})->with([
    'E.164' => '+258841234567',
    'local' => '841234567',
    'spaced' => '84 123 4567',
    'with country code and spaces' => '258 84 123 4567',
]);

test('users can not log in with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('users can not log in with an e-mail address', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'phone' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('users with two-factor enabled are sent to the challenge', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
})->skip(fn () => ! Features::enabled(Features::twoFactorAuthentication()));

test('users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('self-registration and e-mail verification routes do not exist', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/email/verify')->assertNotFound();
});
