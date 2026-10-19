<?php

use App\Domain\Scheduling\ConstraintChecker;
use App\Domain\Scheduling\ScheduleGenerator;
use App\Domain\Scheduling\SchedulingContext;
use App\Domain\Scheduling\Slot;
use App\Domain\Scheduling\Violation;
use App\Enums\Frequency;
use App\Enums\Level;
use Tests\Support\Scheduling as S;

function generate(SchedulingContext $context, array $slots): array
{
    return (new ScheduleGenerator)->generate($context, $slots, '2026-11-01', '2026-11-30');
}

/**
 * @param  list<array{slot: Slot, volunteerId: int}>  $assignments
 * @return array<int, int> volunteer id => assignments
 */
function loadPerVolunteer(array $assignments): array
{
    $load = [];
    foreach ($assignments as $assignment) {
        $load[$assignment['volunteerId']] = ($load[$assignment['volunteerId']] ?? 0) + 1;
    }
    ksort($load);

    return $load;
}

test('generated assignments never break a hard rule', function () {
    $volunteers = [
        S::volunteer(1, frequency: Frequency::Monthly),
        S::volunteer(2, availability: ['2|evening']),
        S::volunteer(3, levels: [S::CAMERA => Level::Beginner]),
        S::volunteer(4, frequency: Frequency::Occasional),
        S::volunteer(5, isActive: false),
        S::volunteer(6, levels: [S::AUDIO => Level::Advanced]),
    ];
    $absences = [['volunteerId' => 3, 'from' => '2026-11-08', 'to' => '2026-11-15']];
    $slots = [...S::novemberSundays(S::CAMERA, 2, Level::Intermediate), ...S::novemberSundays(S::AUDIO)];

    $result = generate(new SchedulingContext($volunteers, $absences), $slots);

    // Re-check every assignment against the others, as a manual reviewer would.
    $checker = new ConstraintChecker;
    foreach ($result['assignments'] as $index => $assignment) {
        $others = array_values(array_filter($result['assignments'], fn ($a, $i) => $i !== $index, ARRAY_FILTER_USE_BOTH));
        $context = new SchedulingContext($volunteers, $absences, array_map(fn ($a) => S::booking($a['volunteerId'], $a['slot']), $others));
        $volunteer = $context->volunteer($assignment['volunteerId']);

        expect($checker->check($volunteer, $assignment['slot'], $context))->toBe([]);
    }

    expect($result['gaps']->isEmpty())->toBeFalse();
});

test('the same input always gives the same schedule', function () {
    $volunteers = array_map(fn (int $id) => S::volunteer($id), range(1, 6));
    $slots = S::novemberSundays(S::CAMERA, 2);

    $first = generate(new SchedulingContext($volunteers), $slots);
    $second = generate(new SchedulingContext($volunteers), $slots);

    expect(array_column($second['assignments'], 'volunteerId'))->toBe(array_column($first['assignments'], 'volunteerId'));
});

test('equally available volunteers differ by at most one assignment (SC-005)', function () {
    $volunteers = array_map(fn (int $id) => S::volunteer($id), range(1, 4));

    $result = generate(new SchedulingContext($volunteers), S::novemberSundays(S::CAMERA, 2));

    $load = loadPerVolunteer($result['assignments']);

    expect(array_sum($load))->toBe(10)
        ->and(max($load) - min($load))->toBeLessThanOrEqual(1);
});

test('recent assignments count towards fairness', function () {
    $volunteers = [S::volunteer(1), S::volunteer(2)];
    $october = S::booking(1, S::slot(90, 90, '2026-10-25'));

    $result = generate(new SchedulingContext($volunteers, [], [$october]), [S::slot(10, 1)]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('the area of greatest interest wins between equals', function () {
    $volunteers = [S::volunteer(1), S::volunteer(2, preferred: [S::CAMERA])];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1)]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('volunteers with variable hours come after regular availability', function () {
    $volunteers = [S::volunteer(1, availability: [], variableHours: true), S::volunteer(2)];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1)]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('a beginner is paired with someone who can support others', function () {
    $volunteers = [
        S::volunteer(1, levels: [S::CAMERA => Level::Beginner], preferred: [S::CAMERA]),
        S::volunteer(2),
        S::volunteer(3, canSupportOthers: true),
    ];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1, quantity: 2)]);

    expect(array_column($result['assignments'], 'volunteerId'))->toContain(1)->toContain(3)->not->toContain(2);
});

test('unfilled positions are reported with the closest candidates and why', function () {
    $volunteers = [
        S::volunteer(1, availability: ['2|evening']),
        S::volunteer(2, levels: [S::AUDIO => Level::Advanced]),
    ];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1, minLevel: Level::Intermediate)]);
    $gap = $result['gaps']->all()[0];

    expect($result['assignments'])->toBe([])
        ->and($gap['missing'])->toBe(1)
        ->and($gap['suggestions'][0]['volunteerId'])->toBe(1)
        ->and($gap['suggestions'][0]['violations'])->toBe([Violation::Availability]);
});

test('existing bookings (locked or manual) are respected', function () {
    $volunteers = [S::volunteer(1), S::volunteer(2)];
    $locked = S::booking(1, S::slot(10, 1));

    $result = generate(new SchedulingContext($volunteers, [], [$locked]), [S::slot(11, 1, roleId: S::CAMERA)]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('with enough people at least 95% of positions are filled (SC-004)', function () {
    $volunteers = [];
    foreach (range(1, 14) as $id) {
        $volunteers[] = S::volunteer(
            $id,
            levels: [S::CAMERA => Level::cases()[$id % 4], S::AUDIO => Level::cases()[($id + 1) % 4]],
            frequency: [Frequency::Weekly, Frequency::TwoThreeMonthly, Frequency::Monthly][$id % 3],
            canSupportOthers: $id % 5 === 0,
            preferred: [$id % 2 === 0 ? S::CAMERA : S::AUDIO],
        );
    }
    $slots = [...S::novemberSundays(S::CAMERA, 3), ...S::novemberSundays(S::AUDIO, 1, Level::Intermediate)];

    $result = generate(new SchedulingContext($volunteers), $slots);

    $needed = 5 * 4;
    expect(count($result['assignments']) / $needed)->toBeGreaterThanOrEqual(0.95);
});
