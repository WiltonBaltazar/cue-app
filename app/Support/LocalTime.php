<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Conversions between Mozambique local time (how people think about events) and UTC
 * (how they are stored).
 */
class LocalTime
{
    public static function zone(): string
    {
        return (string) config('app.display_timezone');
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone())->startOfDay();
    }

    /**
     * A local calendar date plus a local "H:i" / "H:i:s" time, as a UTC instant.
     */
    public static function toUtc(CarbonImmutable|string $date, string $time): CarbonImmutable
    {
        $day = $date instanceof CarbonImmutable ? $date->toDateString() : $date;

        return CarbonImmutable::parse("{$day} {$time}", self::zone())->utc();
    }

    /**
     * A stored UTC instant as local "H:i".
     */
    public static function timeOf(CarbonImmutable $utc): string
    {
        return $utc->setTimezone(self::zone())->format('H:i');
    }
}
