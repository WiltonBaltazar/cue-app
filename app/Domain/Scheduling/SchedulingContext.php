<?php

namespace App\Domain\Scheduling;

/**
 * Everything the rules look at, in memory: volunteers, absences and bookings. Bookings
 * should cover the whole calendar months touched by the period (for monthly caps) plus
 * the 4 weeks before it (for recent load).
 */
final class SchedulingContext
{
    /** @var array<int, VolunteerProfile> */
    private array $volunteers = [];

    /** @var array<int, list<array{0: string, 1: string}>> volunteer id => [from, to] local dates */
    private array $absences = [];

    /** @var array<int, list<Booking>> volunteer id => bookings */
    private array $bookings = [];

    /**
     * @param  list<VolunteerProfile>  $volunteers
     * @param  list<array{volunteerId: int, from: string, to: string}>  $absences
     * @param  list<Booking>  $bookings
     */
    public function __construct(array $volunteers = [], array $absences = [], array $bookings = [])
    {
        foreach ($volunteers as $volunteer) {
            $this->volunteers[$volunteer->id] = $volunteer;
        }

        foreach ($absences as $absence) {
            $this->absences[$absence['volunteerId']][] = [$absence['from'], $absence['to']];
        }

        foreach ($bookings as $booking) {
            $this->add($booking);
        }
    }

    public function add(Booking $booking): void
    {
        $this->bookings[$booking->volunteerId][] = $booking;
    }

    /**
     * Drop a booking, e.g. the one being replaced in a manual edit.
     */
    public function remove(int $volunteerId, int $eventId): void
    {
        $this->bookings[$volunteerId] = array_values(array_filter(
            $this->bookings[$volunteerId] ?? [],
            fn (Booking $booking): bool => $booking->eventId !== $eventId,
        ));
    }

    public function volunteer(int $id): ?VolunteerProfile
    {
        return $this->volunteers[$id] ?? null;
    }

    /**
     * @return array<int, VolunteerProfile> ordered by id (stable for determinism)
     */
    public function volunteers(): array
    {
        $volunteers = $this->volunteers;
        ksort($volunteers);

        return $volunteers;
    }

    /**
     * @return list<Booking>
     */
    public function bookingsOf(int $volunteerId): array
    {
        return $this->bookings[$volunteerId] ?? [];
    }

    /**
     * @return list<Booking>
     */
    public function bookingsAt(int $eventId, int $roleId): array
    {
        $found = [];

        foreach ($this->bookings as $bookings) {
            foreach ($bookings as $booking) {
                if ($booking->eventId === $eventId && $booking->roleId === $roleId) {
                    $found[] = $booking;
                }
            }
        }

        return $found;
    }

    public function isAbsentOn(int $volunteerId, string $date): bool
    {
        foreach ($this->absences[$volunteerId] ?? [] as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bookings within the window [from, to] (local dates), used as "recent load".
     */
    public function loadOf(int $volunteerId, string $from, string $to): int
    {
        return count(array_filter(
            $this->bookingsOf($volunteerId),
            fn (Booking $booking): bool => $booking->date >= $from && $booking->date <= $to,
        ));
    }
}
