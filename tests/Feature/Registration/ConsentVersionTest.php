<?php

use App\Models\ConsentVersion;
use App\Models\User;
use App\Models\Volunteer;

test('coordinators publish a new declaration version', function () {
    $coordinator = User::factory()->coordinator()->create();

    $this->actingAs($coordinator)
        ->post(route('coord.consent.store'), ['body' => 'Declaro que as informações são verdadeiras e autorizo o uso dos meus dados para organizar as escalas, incluindo fotografias.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $current = ConsentVersion::current();

    expect($current->version)->toBe(2)
        ->and($current->published_by)->toBe($coordinator->id);
});

test('volunteers must accept a new version before using the app', function () {
    $volunteer = Volunteer::factory()->active()->create();
    ConsentVersion::query()->create(['version' => 2, 'body' => 'Versão 2', 'published_at' => now()]);

    $this->actingAs($volunteer->user)
        ->get(route('home'))
        ->assertRedirect(route('volunteer.consent'));

    $this->actingAs($volunteer->user)
        ->get(route('volunteer.consent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/consent')
            ->where('declaration.version', 2));

    $this->actingAs($volunteer->user)
        ->post(route('volunteer.consent.accept'), ['consent_version' => 2, 'consent_accepted' => true])
        ->assertRedirect(route('home'));

    expect($volunteer->refresh()->consent_version)->toBe(2);

    $this->actingAs($volunteer->user)->get(route('home'))->assertOk();
});

test('accepting requires ticking the declaration', function () {
    $volunteer = Volunteer::factory()->active()->create();
    ConsentVersion::query()->create(['version' => 2, 'body' => 'Versão 2', 'published_at' => now()]);

    $this->actingAs($volunteer->user)
        ->post(route('volunteer.consent.accept'), ['consent_version' => 2, 'consent_accepted' => false])
        ->assertSessionHasErrors('consent_accepted');

    expect($volunteer->refresh()->consent_version)->toBe(1);
});

test('volunteers can still log out while consent is outstanding', function () {
    $volunteer = Volunteer::factory()->active()->create();
    ConsentVersion::query()->create(['version' => 2, 'body' => 'Versão 2', 'published_at' => now()]);

    $this->actingAs($volunteer->user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});
