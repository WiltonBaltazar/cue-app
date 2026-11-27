<?php

use App\Models\User;
use App\Models\Volunteer;

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

test('a pending volunteer renaming the account renames the volunteer record coordinators see', function () {
    $volunteer = Volunteer::factory()->pending()->create();
    $user = $volunteer->user;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ana Machava', 'email' => ''])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Ana Machava')
        ->and($volunteer->refresh()->full_name)->toBe('Ana Machava');
});

test('an approved volunteer renaming the account renames the volunteer record too', function () {
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Ana Machava']);
    $user = $volunteer->user;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ana Machava Cossa', 'email' => ''])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Ana Machava Cossa')
        ->and($volunteer->refresh()->full_name)->toBe('Ana Machava Cossa');
});

test('sync-names copies account names onto volunteer records that drifted', function () {
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Nome Antigo']);
    $volunteer->user->updateQuietly(['name' => 'Nome Novo']);

    $this->artisan('volunteers:sync-names', ['--dry-run' => true])->assertSuccessful();
    expect($volunteer->refresh()->full_name)->toBe('Nome Antigo');

    $this->artisan('volunteers:sync-names')->assertSuccessful();
    expect($volunteer->refresh()->full_name)->toBe('Nome Novo');
});
