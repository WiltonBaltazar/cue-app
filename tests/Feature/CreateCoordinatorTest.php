<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the first coordinator can be created from the command line', function () {
    $this->artisan('coordinators:create', ['phone' => '84 123 4567', '--name' => 'Ana Coordenadora'])
        ->expectsQuestion('Palavra-passe (mínimo 8 caracteres)', 'segredo-forte-1')
        ->assertSuccessful();

    $user = User::query()->where('phone', '+258841234567')->sole();

    expect($user->role)->toBe(UserRole::Coordinator)
        ->and(Hash::check('segredo-forte-1', $user->password))->toBeTrue();
});

test('an existing account is made coordinator only after confirmation', function () {
    $user = User::factory()->create(['phone' => '+258841234567', 'role' => UserRole::Volunteer]);

    $this->artisan('coordinators:create', ['phone' => '841234567'])
        ->expectsConfirmation("+258841234567 já tem conta ({$user->name}). Tornar coordenador?", 'no')
        ->assertFailed();
    expect($user->fresh()->role)->toBe(UserRole::Volunteer);

    $this->artisan('coordinators:create', ['phone' => '841234567'])
        ->expectsConfirmation("+258841234567 já tem conta ({$user->name}). Tornar coordenador?", 'yes')
        ->assertSuccessful();
    expect($user->fresh()->role)->toBe(UserRole::Coordinator);
});

test('short passwords and invalid numbers are refused', function () {
    $this->artisan('coordinators:create', ['phone' => '123'])->assertFailed();

    $this->artisan('coordinators:create', ['phone' => '841234567', '--name' => 'Ana'])
        ->expectsQuestion('Palavra-passe (mínimo 8 caracteres)', 'curta')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
