<?php

use App\Models\User;
use App\Models\Volunteer;
use Spatie\Activitylog\Models\Activity;

/*
| FR-026 / FR-031: sensitive fields are visible only to coordinators and the
| volunteer themself.
*/

beforeEach(function () {
    $this->volunteer = Volunteer::factory()->active()->create([
        'motivations' => 'Motivação privada da Ana',
        'commitments' => 'Compromisso privado',
    ]);
});

test('the volunteer sees their own sensitive data', function () {
    $this->actingAs($this->volunteer->user)
        ->get(route('volunteer.profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('volunteer/profile/edit')
            ->where('volunteer.motivations', 'Motivação privada da Ana')
            ->where('volunteer.commitments', 'Compromisso privado'));
});

test('coordinators see the full profile and the view is audited', function () {
    $coordinator = User::factory()->coordinator()->create();

    $this->actingAs($coordinator)
        ->get(route('coord.registrations.show', $this->volunteer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('coord/registrations/show')
            ->where('volunteer.motivations', 'Motivação privada da Ana'));

    $view = Activity::query()->where('event', 'viewed_sensitive_profile')->first();

    expect($view?->subject_id)->toBe($this->volunteer->id)
        ->and($view?->causer_id)->toBe($coordinator->id)
        ->and(json_encode($view?->properties))->not->toContain('Motivação');
});

test("other volunteers can not open someone else's profile", function () {
    $other = Volunteer::factory()->active()->create();

    $this->actingAs($other->user)
        ->get(route('coord.registrations.show', $this->volunteer))
        ->assertForbidden();

    $this->actingAs($other->user)
        ->get(route('coord.volunteers.edit', $this->volunteer))
        ->assertForbidden();
});

test("a volunteer's own pages never include other volunteers' data", function () {
    $other = Volunteer::factory()->active()->create(['motivations' => 'Segredo do outro']);

    foreach ([route('home'), route('volunteer.profile.edit'), route('profile.edit')] as $url) {
        $content = $this->actingAs($this->volunteer->user)->get($url)->getContent();

        expect($content)->not->toContain('Segredo do outro')
            ->and($content)->not->toContain((string) $other->user?->phone);
    }
});
