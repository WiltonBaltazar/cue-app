<?php

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use Carbon\CarbonImmutable;

test('levels are ordered and meet minimums', function () {
    expect(Level::Mentor->meets(Level::Advanced))->toBeTrue()
        ->and(Level::Intermediate->meets(Level::Intermediate))->toBeTrue()
        ->and(Level::Beginner->meets(Level::Intermediate))->toBeFalse()
        ->and(Level::Beginner->meets(null))->toBeTrue();
});

test('frequencies map to the caps in the spec assumptions', function () {
    expect(Frequency::Weekly->monthlyCap())->toBeNull()
        ->and(Frequency::Weekly->weeklyCap())->toBe(1)
        ->and(Frequency::TwoThreeMonthly->monthlyCap())->toBe(3)
        ->and(Frequency::Monthly->monthlyCap())->toBe(1)
        ->and(Frequency::Occasional->allowsAutomaticAssignment())->toBeFalse();
});

test('period is derived from local start time', function (string $time, Period $expected) {
    expect(Period::fromTime(CarbonImmutable::parse("2026-11-01 {$time}")))->toBe($expected);
})->with([
    ['07:30', Period::Morning],
    ['11:59', Period::Morning],
    ['12:00', Period::Afternoon],
    ['17:59', Period::Afternoon],
    ['18:00', Period::Evening],
]);
