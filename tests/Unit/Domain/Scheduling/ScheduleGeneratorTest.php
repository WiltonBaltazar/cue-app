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

        expect(Violation::hard($checker->check($volunteer, $assignment['slot'], $context)))->toBe([]);
    }

    // Volunteer 1 (monthly) and 4 (occasional) go over their frequency rather than leave gaps.
    expect($result['gaps']->isEmpty())->toBeTrue();
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

test('people within their frequency are chosen before anyone goes over theirs', function () {
    $volunteers = [S::volunteer(1, frequency: Frequency::Monthly), S::volunteer(2, frequency: Frequency::Monthly)];
    $earlier = S::booking(1, S::slot(90, 90, '2026-11-01'));

    $result = generate(new SchedulingContext($volunteers, [], [$earlier]), [S::slot(10, 1, '2026-11-15')]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('frequency never leaves a position empty: someone goes over theirs instead', function () {
    $volunteers = [S::volunteer(1, frequency: Frequency::Monthly), S::volunteer(2, frequency: Frequency::Monthly)];

    $result = generate(new SchedulingContext($volunteers), S::novemberSundays(S::CAMERA));

    expect($result['assignments'])->toHaveCount(5)
        ->and($result['gaps']->isEmpty())->toBeTrue()
        ->and(max(loadPerVolunteer($result['assignments'])) - min(loadPerVolunteer($result['assignments'])))->toBeLessThanOrEqual(1);
});

test('"Ocasionalmente" volunteers come after the rest, once a month', function () {
    $volunteers = [S::volunteer(1, frequency: Frequency::Monthly), S::volunteer(2, frequency: Frequency::Occasional)];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1, '2026-11-01'), S::slot(11, 2, '2026-11-08'), S::slot(12, 3, '2026-11-15')]);

    expect(array_column($result['assignments'], 'volunteerId'))->toBe([1, 2, 1]);
});

test('someone below the minimum level fills a place nobody qualified can take', function () {
    $volunteers = [S::volunteer(1, levels: [S::CAMERA => Level::Beginner])];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1, minLevel: Level::Advanced)]);

    expect(array_column($result['assignments'], 'volunteerId'))->toBe([1])
        ->and($result['gaps']->isEmpty())->toBeTrue();
});

test('qualified people come before anyone below the minimum level', function () {
    $volunteers = [S::volunteer(1, levels: [S::CAMERA => Level::Beginner]), S::volunteer(2, levels: [S::CAMERA => Level::Advanced])];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1, minLevel: Level::Advanced)]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('a clash with another event is the last resort', function () {
    $volunteers = [S::volunteer(1), S::volunteer(2, levels: [S::CAMERA => Level::Beginner])];
    $elsewhere = S::booking(1, S::slot(90, 90));

    $result = generate(new SchedulingContext($volunteers, [], [$elsewhere]), [S::slot(10, 1, minLevel: Level::Advanced)]);

    // Below the level beats being in two places at once.
    expect($result['assignments'][0]['volunteerId'])->toBe(2);

    $alone = generate(new SchedulingContext([S::volunteer(1)], [], [$elsewhere]), [S::slot(10, 1)]);

    expect(array_column($alone['assignments'], 'volunteerId'))->toBe([1]);
});

test('role and availability still block: those places stay empty', function () {
    $volunteers = [S::volunteer(1, availability: ['2|evening']), S::volunteer(2, levels: [S::AUDIO => Level::Advanced])];

    $result = generate(new SchedulingContext($volunteers), [S::slot(10, 1)]);

    expect($result['assignments'])->toBe([])
        ->and($result['gaps']->isEmpty())->toBeFalse();
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

test('nobody is left out when schedules are short and there are more people than places', function () {
    $volunteers = array_map(fn (int $id) => S::volunteer($id), range(1, 10));
    $bookings = [];
    $load = [];

    // One place every Sunday, generated a week at a time. Like the loader, only the last 28
    // days are given as bookings.
    for ($week = 0; $week < 20; $week++) {
        $date = date('Y-m-d', (int) strtotime("2026-11-01 +{$week} weeks"));
        $windowFrom = date('Y-m-d', (int) strtotime("{$date} -28 days"));
        $recent = array_values(array_filter($bookings, fn ($booking) => $booking->date >= $windowFrom));
        $lastServed = [];
        foreach ($bookings as $booking) {
            if ($booking->date < $windowFrom) {
                $lastServed[$booking->volunteerId] = $booking->date;
            }
        }

        $slot = S::slot(100 + $week, 100 + $week, $date);
        $result = (new ScheduleGenerator)->generate(new SchedulingContext($volunteers, [], $recent, $lastServed), [$slot], $date, $date);
        $chosen = $result['assignments'][0]['volunteerId'];
        $bookings[] = S::booking($chosen, $slot);
        $load[$chosen] = ($load[$chosen] ?? 0) + 1;
    }

    expect($load)->toHaveCount(10)->and(max($load))->toBe(2);
});

test('everyone available serves at least once a month when there are places', function () {
    $volunteers = array_map(fn (int $id) => S::volunteer($id, frequency: $id <= 6 ? Frequency::Weekly : Frequency::Monthly), range(1, 9));
    $volunteers[] = S::volunteer(10, frequency: Frequency::Occasional);

    $result = generate(new SchedulingContext($volunteers), S::novemberSundays(S::CAMERA, 2));

    expect(loadPerVolunteer($result['assignments']))->toBe(array_fill_keys(range(1, 10), 1));
});

test('the only place someone can take is kept for them', function () {
    // Two places, equally scarce. Person 1 ranks first on the Sunday but can also come on
    // the Tuesday; person 2 can only come on the Sunday; person 3 already served this month.
    $volunteers = [
        S::volunteer(1, availability: ['7|morning', '2|evening']),
        S::volunteer(2, availability: ['7|morning']),
        S::volunteer(3, availability: ['2|evening']),
    ];
    $served = [S::booking(3, S::slot(90, 90, '2026-11-24', period: 'evening', from: '17:00', until: '20:00'))];
    $slots = [S::slot(10, 1), S::slot(11, 2, '2026-11-03', period: 'evening', from: '17:00', until: '20:00')];

    $result = generate(new SchedulingContext($volunteers, [], $served), $slots);

    expect(loadPerVolunteer($result['assignments']))->toBe([1 => 1, 2 => 1]);
});

test('someone who already served this month waits for those who did not', function () {
    $volunteers = [S::volunteer(1), S::volunteer(2)];
    // Person 2 served more lately, but in October; person 1 already has a November shift.
    $bookings = [
        S::booking(1, S::slot(90, 90, '2026-11-01')),
        S::booking(2, S::slot(91, 91, '2026-10-18')),
        S::booking(2, S::slot(92, 92, '2026-10-25')),
    ];

    $result = generate(new SchedulingContext($volunteers, [], $bookings), [S::slot(10, 1, '2026-11-15')]);

    expect($result['assignments'][0]['volunteerId'])->toBe(2);
});

test('absent people are not forced into their month', function () {
    $absences = [['volunteerId' => 2, 'from' => '2026-11-01', 'to' => '2026-11-30']];

    $result = generate(new SchedulingContext([S::volunteer(1), S::volunteer(2)], $absences), S::novemberSundays());

    expect(array_keys(loadPerVolunteer($result['assignments'])))->toBe([1]);
});
