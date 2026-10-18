<?php

use App\Models\User;

test('unknown pages show the Portuguese 404 page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('errors/show')->where('status', 404));
});

test('forbidden pages show the Portuguese 403 page', function () {
    $this->actingAs(User::factory()->volunteer()->create())
        ->get(route('coord.dashboard'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('errors/show')->where('status', 403));
});

test('JSON requests keep JSON errors', function () {
    $this->actingAs(User::factory()->volunteer()->create())
        ->getJson('/app-api/nao-existe')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
