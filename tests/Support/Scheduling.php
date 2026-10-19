<?php

namespace Tests\Support;

use App\Domain\Scheduling\Booking;
use App\Domain\Scheduling\Slot;
use App\Domain\Scheduling\VolunteerProfile;
use App\Enums\Frequency;
use App\Enums\Level;

/**
 * Builders for scheduling unit tests. Role 1 = camera, 2 = audio.
 */
final class Scheduling
{
    public const CAMERA = 1;

    public const AUDIO = 2;

    /**
     * @param  array<int, Level>  $levels
     * @param  list<string>  $availability  e.g. ["7|morning"]
     * @param  list<int>  $preferred
     */
    public static function volunteer(
        int $id,
        array $levels = [self::CAMERA => Level::Intermediate],
        array $availability = ['7|morning', '2|evening'],
        Frequency $frequency = Frequency::Weekly,
        bool $variableHours = false,
        bool $canSupportOthers = false,
        bool $isActive = true,
        array $preferred = [],
    ): VolunteerProfile {
        return new VolunteerProfile(
            id: $id,
            isActive: $isActive,
            frequency: $frequency,
            variableHours: $variableHours,
            canSupportOthers: $canSupportOthers,
            availability: array_fill_keys($availability, true),
            levels: $levels,
            preferredRoleIds: $preferred,
        );
    }

    /**
     * A slot on a local date, 07:30–12:00 (Sunday morning service) unless times given.
     */
    public static function slot(
        int $positionId,
        int $eventId,
        string $date = '2026-11-01',
        int $roleId = self::CAMERA,
        ?Level $minLevel = null,
        int $quantity = 1,
        string $period = 'morning',
        string $from = '07:30',
        string $until = '12:00',
    ): Slot {
        return new Slot(
            positionId: $positionId,
            eventId: $eventId,
            roleId: $roleId,
            minLevel: $minLevel,
            date: $date,
            weekday: (int) date('N', (int) strtotime($date)),
            period: $period,
            busyFrom: (int) strtotime("{$date} {$from} UTC"),
            busyUntil: (int) strtotime("{$date} {$until} UTC"),
            quantity: $quantity,
            label: "Evento {$eventId}",
        );
    }

    public static function booking(int $volunteerId, Slot $slot): Booking
    {
        return Booking::forSlot($volunteerId, $slot);
    }

    /**
     * Sunday-morning slots for every Sunday of November 2026.
     *
     * @return list<Slot>
     */
    public static function novemberSundays(int $roleId = self::CAMERA, int $quantity = 1, ?Level $minLevel = null): array
    {
        $slots = [];

        foreach (['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'] as $index => $date) {
            $slots[] = self::slot(positionId: 100 + $index * 10 + $roleId, eventId: 100 + $index, date: $date, roleId: $roleId, minLevel: $minLevel, quantity: $quantity);
        }

        return $slots;
    }
}
