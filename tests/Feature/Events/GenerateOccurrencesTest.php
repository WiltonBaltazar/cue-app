<?php

use App\Actions\Events\GenerateOccurrences;
use App\Enums\EventType;
use App\Enums\Level;
use App\Models\ActivityTemplate;
use App\Models\Event;
use App\Models\TeamRole;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Wednesday 4 Nov 2026, 10:00 in Maputo.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'Africa/Maputo'));
    $this->camera = TeamRole::query()->where('slug', 'operacao-de-camara')->firstOrFail();
    $this->audio = TeamRole::query()->where('slug', 'audio')->firstOrFail();
});

function sundayTemplate(array $attributes = []): ActivityTemplate
{
    return ActivityTemplate::factory()
        ->withPosition(test()->camera, 3)
        ->withPosition(test()->audio, 1, Level::Intermediate)
        ->create(['starts_on' => '2026-11-01', ...$attributes]);
}

test('occurrences are generated for every matching date within the horizon', function () {
    $template = sundayTemplate();

    app(GenerateOccurrences::class)->handle();

    $events = Event::query()->orderBy('date')->get();

    // Sundays from 8 Nov 2026 to 4 Feb 2027 (3 months).
    expect($events)->toHaveCount(13)
        ->and($events->first()?->date->toDateString())->toBe('2026-11-08')
        ->and($events->last()?->date->toDateString())->toBe('2027-01-31')
        ->and($events->every(fn (Event $event) => $event->type === EventType::Recurring && $event->activity_template_id === $template->id))->toBeTrue();
});

test('times are stored in UTC from the local template times, with teardown', function () {
    sundayTemplate();

    app(GenerateOccurrences::class)->handle();

    $event = Event::query()->orderBy('date')->firstOrFail();

    expect(LocalTime::timeOf($event->arrival_at))->toBe('07:30')
        ->and(LocalTime::timeOf($event->starts_at))->toBe('08:30')
        ->and(LocalTime::timeOf($event->ends_at))->toBe('11:30')
        ->and(LocalTime::timeOf($event->teardown_until))->toBe('12:00')
        ->and($event->starts_at->utc()->format('H:i'))->toBe('06:30');
});

test('positions are copied from the template', function () {
    sundayTemplate();

    app(GenerateOccurrences::class)->handle();

    $event = Event::query()->with('positions')->firstOrFail();

    expect($event->requiredPeople())->toBe(4)
        ->and($event->positions->firstWhere('team_role_id', $this->audio->id)?->min_level)->toBe(Level::Intermediate);
});

test('the horizon follows the setting', function () {
    app(Settings::class)->set('event_horizon_months', 1);
    sundayTemplate();

    app(GenerateOccurrences::class)->handle();

    expect(Event::query()->count())->toBe(4);
});

test('running again creates nothing new', function () {
    sundayTemplate();

    app(GenerateOccurrences::class)->handle();
    app(GenerateOccurrences::class)->handle();

    expect(Event::query()->count())->toBe(13);
});

test('every other week is supported', function () {
    sundayTemplate(['interval_weeks' => 2]);

    app(GenerateOccurrences::class)->handle();

    expect(Event::query()->orderBy('date')->pluck('date')->map->toDateString()->take(3)->all())
        ->toBe(['2026-11-15', '2026-11-29', '2026-12-13']);
});

test('the first Sunday of every month is supported', function () {
    sundayTemplate(['month_week' => 1]);

    app(GenerateOccurrences::class)->handle();

    // 1 Nov is already past; the horizon ends on 4 Feb, before 7 Feb.
    expect(Event::query()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-12-06', '2027-01-03']);
});

test('the last Sunday of every month is supported', function () {
    sundayTemplate(['month_week' => -1]);

    app(GenerateOccurrences::class)->handle();

    expect(Event::query()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-29', '2026-12-27', '2027-01-31']);
});

test('template edits reach future non-customised occurrences only', function () {
    $template = sundayTemplate();
    app(GenerateOccurrences::class)->handle();

    $customised = Event::query()->orderBy('date')->skip(1)->firstOrFail();
    $customised->forceFill(['is_customized' => true])->save();

    $template->update(['start_time' => '09:00', 'location' => 'Pavilhão']);
    app(GenerateOccurrences::class)->handle($template);

    $first = Event::query()->orderBy('date')->firstOrFail();

    expect(LocalTime::timeOf($first->starts_at))->toBe('09:00')
        ->and($first->location)->toBe('Pavilhão')
        ->and(LocalTime::timeOf($customised->fresh()->starts_at))->toBe('08:30')
        ->and($customised->fresh()->location)->toBe('Templo sede');
});

test('past occurrences are never changed', function () {
    $template = sundayTemplate();
    app(GenerateOccurrences::class)->handle();
    $first = Event::query()->orderBy('date')->firstOrFail();

    $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Maputo'));
    $template->update(['location' => 'Pavilhão']);
    app(GenerateOccurrences::class)->handle($template);

    expect($first->fresh()->location)->toBe('Templo sede');
});

test('changing the weekday removes future occurrences that no longer match', function () {
    $template = sundayTemplate();
    app(GenerateOccurrences::class)->handle();

    $template->update(['weekday' => 2, 'period' => 'evening']);
    app(GenerateOccurrences::class)->handle($template);

    expect(Event::query()->get()->every(fn (Event $event) => $event->date->dayOfWeekIso === 2))->toBeTrue();
});

test('deactivating a template removes its future non-customised occurrences', function () {
    $template = sundayTemplate();
    app(GenerateOccurrences::class)->handle();
    Event::query()->orderBy('date')->firstOrFail()->forceFill(['is_customized' => true])->save();

    $template->update(['is_active' => false]);
    app(GenerateOccurrences::class)->handle($template);

    expect(Event::query()->count())->toBe(1);
});

test('the daily command generates occurrences', function () {
    sundayTemplate();

    $this->artisan('events:generate')->assertSuccessful();

    expect(Event::query()->count())->toBe(13);
});
