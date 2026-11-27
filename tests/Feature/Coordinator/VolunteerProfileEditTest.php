<?php

use App\Models\TeamRole;
use App\Models\User;
use App\Models\Volunteer;

function profilePayload(TeamRole $role, array $overrides = []): array
{
    return [
        'skills' => [['team_role_id' => $role->id]],
        'experience_role_ids' => [$role->id],
        'availability' => [['weekday' => 2, 'period' => 'evening']],
        'max_frequency' => 'weekly',
        'training_availability' => 'regular',
        ...$overrides,
    ];
}

test('a coordinator opens and saves a volunteer profile, and the name changes everywhere', function () {
    $coordinator = User::factory()->coordinator()->create();
    $role = TeamRole::factory()->create();
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Ana Machava']);

    $this->actingAs($coordinator)
        ->get(route('coord.volunteers.profile.edit', $volunteer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('coord/volunteers/profile'));

    $this->actingAs($coordinator)
        ->put(route('coord.volunteers.profile.update', $volunteer), profilePayload($role, [
            'full_name' => 'Ana Machava Cossa',
            'birth_date' => '1990-05-04',
            'neighbourhood' => 'Malhangalene',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('coord.volunteers.edit', $volunteer));

    $volunteer->refresh();

    expect($volunteer->full_name)->toBe('Ana Machava Cossa')
        ->and($volunteer->user->name)->toBe('Ana Machava Cossa')
        ->and($volunteer->birth_date)->toStartWith('1990-05-04')
        ->and($volunteer->neighbourhood)->toBe('Malhangalene');
});

test('an approved volunteer can correct their own name, but not their birth date', function () {
    $role = TeamRole::factory()->create();
    $volunteer = Volunteer::factory()->active()->create(['full_name' => 'Ana Machava', 'birth_date' => '1995-01-01']);

    $this->actingAs($volunteer->user)
        ->put(route('volunteer.profile.update'), profilePayload($role, [
            'full_name' => 'Ana M. Cossa',
            'birth_date' => '2000-01-01',
        ]))
        ->assertSessionHasNoErrors();

    $volunteer->refresh();

    expect($volunteer->full_name)->toBe('Ana M. Cossa')
        ->and($volunteer->user->name)->toBe('Ana M. Cossa')
        ->and($volunteer->birth_date)->toStartWith('1995-01-01');
});

test('volunteers cannot open the coordinator profile editor', function () {
    $volunteer = Volunteer::factory()->active()->create();
    $other = Volunteer::factory()->active()->create();

    $this->actingAs($volunteer->user)
        ->get(route('coord.volunteers.profile.edit', $other))
        ->assertForbidden();
});
