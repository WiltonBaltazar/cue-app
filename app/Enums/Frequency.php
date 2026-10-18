<?php

namespace App\Enums;

/**
 * Maximum serving frequency declared by a volunteer (spec assumptions).
 */
enum Frequency: string
{
    case Weekly = 'weekly';
    case TwoThreeMonthly = 'two_three_monthly';
    case Monthly = 'monthly';
    case Occasional = 'occasional';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Todas as semanas',
            self::TwoThreeMonthly => '2 a 3 vezes por mês',
            self::Monthly => '1 vez por mês',
            self::Occasional => 'Ocasionalmente',
        };
    }

    /**
     * Assignments allowed per calendar month in automatic generation (null = no monthly cap).
     */
    public function monthlyCap(): ?int
    {
        return match ($this) {
            self::Weekly => null,
            self::TwoThreeMonthly => 3,
            self::Monthly => 1,
            self::Occasional => 0,
        };
    }

    /**
     * Assignments allowed per ISO week in automatic generation (null = no weekly cap).
     */
    public function weeklyCap(): ?int
    {
        return $this === self::Weekly ? 1 : null;
    }

    /**
     * "Occasionally" volunteers are only ever assigned manually.
     */
    public function allowsAutomaticAssignment(): bool
    {
        return $this !== self::Occasional;
    }
}
