<?php

namespace App\Enums;

use DateTimeInterface;

enum Period: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Manhã',
            self::Afternoon => 'Tarde',
            self::Evening => 'Noite',
        };
    }

    /**
     * Period of a local (Africa/Maputo) start time: before 12:00 morning, before 18:00 afternoon.
     */
    public static function fromTime(DateTimeInterface $localTime): self
    {
        $hour = (int) $localTime->format('G');

        return match (true) {
            $hour < 12 => self::Morning,
            $hour < 18 => self::Afternoon,
            default => self::Evening,
        };
    }
}
