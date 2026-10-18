<?php

use App\Models\User;

test('profile page is displayed with the phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->where('phone', $user->phone));
});

test('name and e-mail can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Ana Machava',
            'email' => 'Ana@Exemplo.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Ana Machava')
        ->and($user->email)->toBe('ana@exemplo.com');
});

test('e-mail is optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('the phone number can not be changed from the account page', function () {
    $user = User::factory()->create(['phone' => '+258841234567']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'phone' => '+258851111111',
    ]);

    expect($user->refresh()->phone)->toBe('+258841234567');
});

test('there is no instant self-deletion of the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/perfil/conta')->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
