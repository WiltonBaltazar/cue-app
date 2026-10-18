<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('security page is displayed after confirming the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/security')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false)
            ->missing('passkeys'));
});

test('security page requires password confirmation', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'nova-senha-2026',
            'password_confirmation' => 'nova-senha-2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('nova-senha-2026', $user->refresh()->password))->toBeTrue();
});

test('passwords shorter than 8 characters are rejected', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])
        ->assertSessionHasErrors('password');
});

test('correct current password is required to update the password', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'nova-senha-2026',
            'password_confirmation' => 'nova-senha-2026',
        ])
        ->assertSessionHasErrors('current_password');
});
