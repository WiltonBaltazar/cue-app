<?php

use App\Enums\SuggestionStatus;
use App\Models\User;
use App\Models\Volunteer;

beforeEach(function () {
    $this->coordinator = User::factory()->coordinator()->create();
});

test('a written suggestion shows up as new for coordinators', function () {
    $volunteer = Volunteer::factory()->active()->create(['suggestions' => 'Ensaiar antes do culto.']);
    Volunteer::factory()->active()->create(['suggestions' => null]);

    expect($volunteer->refresh()->suggestion_status)->toBe(SuggestionStatus::New);

    $this->actingAs($this->coordinator)
        ->get(route('coord.suggestions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/suggestions/index')
            ->where('status', 'new')
            ->has('suggestions', 1)
            ->where('suggestions.0.text', 'Ensaiar antes do culto.'));
});

test('a coordinator moves a suggestion on with a note', function () {
    $volunteer = Volunteer::factory()->active()->create(['suggestions' => 'Ensaiar antes do culto.']);

    $this->actingAs($this->coordinator)
        ->put(route('coord.suggestions.update', $volunteer), ['status' => 'in_progress', 'note' => 'Começa em Dezembro.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('coord.suggestions.index', ['estado' => 'new']));

    $volunteer->refresh();

    expect($volunteer->suggestion_status)->toBe(SuggestionStatus::InProgress)
        ->and($volunteer->suggestion_note)->toBe('Começa em Dezembro.')
        ->and($volunteer->suggestion_handled_by)->toBe($this->coordinator->id);

    $this->actingAs($this->coordinator)
        ->get(route('coord.suggestions.index', ['estado' => 'in_progress']))
        ->assertInertia(fn ($page) => $page->has('suggestions', 1));
});

test('rewriting a suggestion puts it back on the new list', function () {
    $volunteer = Volunteer::factory()->active()->create(['suggestions' => 'Primeira ideia.']);
    $volunteer->forceFill(['suggestion_status' => SuggestionStatus::Done])->save();

    $volunteer->update(['suggestions' => 'Outra ideia.']);

    expect($volunteer->refresh()->suggestion_status)->toBe(SuggestionStatus::New);
});

test('volunteers cannot see or act on suggestions', function () {
    $volunteer = Volunteer::factory()->active()->create(['suggestions' => 'Ideia.']);

    $this->actingAs($volunteer->user)->get(route('coord.suggestions.index'))->assertForbidden();
    $this->actingAs($volunteer->user)
        ->put(route('coord.suggestions.update', $volunteer), ['status' => 'archived'])
        ->assertForbidden();
});
