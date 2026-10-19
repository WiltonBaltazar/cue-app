<?php

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\SchedulingContext;
use App\Domain\Scheduling\Violation;
use App\Enums\Frequency;
use App\Enums\Level;
use Tests\Support\Scheduling as S;

beforeEach(function () {
    $this->checker = new ConstraintChecker;
});

test('an available, skilled, free volunteer breaks no rule', function () {
    $volunteer = S::volunteer(1);

    expect($this->checker->check($volunteer, S::slot(10, 1), new SchedulingContext([$volunteer])))->toBe([]);
});

test('availability is checked by weekday and period', function () {
    $volunteer = S::volunteer(1, availability: ['7|evening']);

    expect($this->checker->check($volunteer, S::slot(10, 1), new SchedulingContext([$volunteer])))
        ->toBe([Violation::Availability]);
});

test('variable hours count as available in every slot', function () {
    $volunteer = S::volunteer(1, availability: [], variableHours: true);

    expect($this->checker->check($volunteer, S::slot(10, 1), new SchedulingContext([$volunteer])))->toBe([]);
});

test('absences block the dates they cover, inclusive', function (string $date, bool $blocked) {
    $volunteer = S::volunteer(1);
    $context = new SchedulingContext([$volunteer], [['volunteerId' => 1, 'from' => '2026-11-01', 'to' => '2026-11-08']]);

    expect(in_array(Violation::Absence, $this->checker->check($volunteer, S::slot(10, 1, $date), $context), true))->toBe($blocked);
})->with([
    ['2026-11-01', true],
    ['2026-11-08', true],
    ['2026-11-15', false],
]);

test('the minimum level is checked per role', function () {
    $volunteer = S::volunteer(1, levels: [S::CAMERA => Level::Advanced, S::AUDIO => Level::Beginner]);
    $context = new SchedulingContext([$volunteer]);

    expect($this->checker->check($volunteer, S::slot(10, 1, roleId: S::CAMERA, minLevel: Level::Advanced), $context))->toBe([])
        ->and($this->checker->check($volunteer, S::slot(11, 1, roleId: S::AUDIO, minLevel: Level::Intermediate), $context))->toBe([Violation::MinLevel]);
});

test('a volunteer without the role is never a fit', function () {
    $volunteer = S::volunteer(1, levels: [S::CAMERA => Level::Mentor]);

    expect($this->checker->check($volunteer, S::slot(10, 1, roleId: S::AUDIO), new SchedulingContext([$volunteer])))
        ->toBe([Violation::Role]);
});

test('weekly volunteers serve at most once per ISO week', function () {
    $volunteer = S::volunteer(1, availability: ['7|morning', '6|morning']);
    $sunday = S::slot(10, 1, '2026-11-08');
    $context = new SchedulingContext([$volunteer], [], [S::booking(1, S::slot(20, 2, '2026-11-07'))]);

    expect($this->checker->check($volunteer, $sunday, $context))->toBe([Violation::FrequencyCap])
        ->and($this->checker->check($volunteer, S::slot(11, 3, '2026-11-15'), $context))->toBe([]);
});

test('monthly caps follow the declared frequency', function (Frequency $frequency, int $alreadyBooked, bool $blocked) {
    $volunteer = S::volunteer(1, frequency: $frequency);
    $bookings = array_map(fn (int $i) => S::booking(1, S::slot(50 + $i, 50 + $i, '2026-11-0'.($i + 1))), range(0, $alreadyBooked - 1));
    $context = new SchedulingContext([$volunteer], [], $alreadyBooked > 0 ? $bookings : []);

    expect(in_array(Violation::FrequencyCap, $this->checker->check($volunteer, S::slot(10, 1, '2026-11-29'), $context), true))->toBe($blocked);
})->with([
    'monthly, none yet' => [Frequency::Monthly, 0, false],
    'monthly, one already' => [Frequency::Monthly, 1, true],
    '2–3 per month, two already' => [Frequency::TwoThreeMonthly, 2, false],
    '2–3 per month, three already' => [Frequency::TwoThreeMonthly, 3, true],
]);

test('occasional volunteers are excluded from automatic generation only', function () {
    $volunteer = S::volunteer(1, frequency: Frequency::Occasional);
    $context = new SchedulingContext([$volunteer]);

    expect($this->checker->check($volunteer, S::slot(10, 1), $context))->toBe([Violation::FrequencyCap])
        ->and($this->checker->check($volunteer, S::slot(10, 1), $context, automatic: false))->toBe([]);
});

test('a volunteer takes only one position per event', function () {
    $volunteer = S::volunteer(1, levels: [S::CAMERA => Level::Advanced, S::AUDIO => Level::Advanced]);
    $context = new SchedulingContext([$volunteer], [], [S::booking(1, S::slot(10, 1, roleId: S::CAMERA))]);

    expect($this->checker->check($volunteer, S::slot(11, 1, roleId: S::AUDIO), $context))->toContain(Violation::SameEvent);
});

test('overlapping events are blocked, including setup and teardown', function () {
    $volunteer = S::volunteer(1);
    $morning = S::slot(10, 1, from: '07:30', until: '12:00');
    $context = new SchedulingContext([$volunteer], [], [S::booking(1, $morning)]);

    expect($this->checker->check($volunteer, S::slot(20, 2, from: '11:30', until: '14:00'), $context))->toContain(Violation::TimeOverlap)
        ->and($this->checker->check($volunteer, S::slot(21, 3, from: '12:00', until: '14:00'), $context))->not->toContain(Violation::TimeOverlap);
});

test('inactive volunteers are excluded', function () {
    $volunteer = S::volunteer(1, isActive: false);

    expect($this->checker->check($volunteer, S::slot(10, 1), new SchedulingContext([$volunteer])))->toBe([Violation::Inactive]);
});
