<?php

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\VolunteerStatus;
use App\Models\ConsentVersion;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScheduleScenario;

beforeEach(function () {
    Notification::fake();
    $this->coordinator = User::factory()->coordinator()->create(['name' => 'Marta Coordenadora']);
    $this->scenario = ScheduleScenario::create();
});

function servingProfile(ScheduleScenario $scenario, array $overrides = []): array
{
    return [
        'full_name' => 'Marta Langa',
        'birth_date' => LocalTime::today()->subYears(35)->toDateString(),
        'skills' => [['team_role_id' => $scenario->camera->id, 'level' => Level::Advanced->value]],
        'availability' => [['weekday' => 7, 'period' => Period::Morning->value]],
        'max_frequency' => Frequency::Weekly->value,
        'consent_accepted' => true,
        'consent_version' => ConsentVersion::currentNumber(),
        ...$overrides,
    ];
}

test('a coordinator who also serves becomes schedulable at once', function () {
    $this->actingAs($this->coordinator->fresh())
        ->post(route('coord.serving-profile.store'), servingProfile($this->scenario))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('coord.volunteers.index'));

    $volunteer = $this->coordinator->fresh()->volunteer;

    expect($volunteer->status)->toBe(VolunteerStatus::Active)
        ->and($volunteer->full_name)->toBe('Marta Langa')
        ->and($volunteer->skills->sole()->level)->toBe(Level::Advanced)
        ->and($this->coordinator->fresh()->name)->toBe('Marta Langa')
        ->and($this->coordinator->fresh()->isCoordinator())->toBeTrue();

    $candidates = $this->actingAs($this->coordinator->fresh())
        ->getJson(route('app-api.positions.candidates', $this->scenario->position))
        ->json('data');

    expect(collect($candidates)->firstWhere('volunteerId', $volunteer->id)['eligible'])->toBeTrue();

    $this->actingAs($this->coordinator->fresh())->post(route('coord.schedules.generate', $this->scenario->schedule));
    expect($volunteer->assignments()->count())->toBe(1);
});

test('once published, the coordinator sees their own shifts', function () {
    $this->actingAs($this->coordinator->fresh())->post(route('coord.serving-profile.store'), servingProfile($this->scenario));
    $this->actingAs($this->coordinator->fresh())->post(route('coord.schedules.generate', $this->scenario->schedule));
    $this->actingAs($this->coordinator->fresh())->post(route('coord.schedules.publish', $this->scenario->schedule));

    $this->actingAs($this->coordinator->fresh())
        ->get(route('volunteer.shifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('volunteer/shifts/index')->has('shifts', 1));
});

test('the serving profile needs a function, availability and the declaration', function () {
    $this->actingAs($this->coordinator->fresh())
        ->post(route('coord.serving-profile.store'), servingProfile($this->scenario, [
            'skills' => [],
            'availability' => [],
            'consent_accepted' => false,
        ]))
        ->assertSessionHasErrors(['skills', 'availability', 'consent_accepted']);

    expect(Volunteer::query()->where('user_id', $this->coordinator->id)->exists())->toBeFalse();
});

test('a coordinator with a profile is sent to edit it, and volunteers can not use the page', function () {
    $this->actingAs($this->coordinator->fresh())->post(route('coord.serving-profile.store'), servingProfile($this->scenario));

    $this->actingAs($this->coordinator->fresh())
        ->get(route('coord.serving-profile.create'))
        ->assertRedirect(route('volunteer.profile.edit'));

    $this->actingAs($this->coordinator->fresh())
        ->post(route('coord.serving-profile.store'), servingProfile($this->scenario))
        ->assertSessionHasErrors('form');

    $volunteer = $this->scenario->volunteer();

    $this->actingAs($volunteer->user)->get(route('coord.serving-profile.create'))->assertForbidden();
});
