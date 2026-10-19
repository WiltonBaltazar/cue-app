<?php

namespace App\Domain\Scheduling;

/**
 * A scheduling rule a candidate would break (FR-011). Hard rules are never broken by
 * automatic generation; coordinators may override them with a note (FR-013). The declared
 * frequency is soft: preferred, but exceeded when nobody else can fill a position.
 */
enum Violation: string
{
    case Inactive = 'inactive';
    case Role = 'role';
    case MinLevel = 'min_level';
    case Availability = 'availability';
    case Absence = 'absence';
    case FrequencyCap = 'frequency_cap';
    case SameEvent = 'same_event';
    case TimeOverlap = 'time_overlap';

    public function message(): string
    {
        return match ($this) {
            self::Inactive => 'Não está activo',
            self::Role => 'Não tem esta função',
            self::MinLevel => 'Nível abaixo do mínimo',
            self::Availability => 'Não está disponível neste dia e período',
            self::Absence => 'Tem uma ausência registada',
            self::FrequencyCap => 'Ultrapassa a frequência que indicou',
            self::SameEvent => 'Já está escalado neste evento',
            self::TimeOverlap => 'Já está escalado noutro evento à mesma hora',
        };
    }

    public function isHard(): bool
    {
        return $this !== self::FrequencyCap;
    }

    /**
     * @param  list<self>  $violations
     * @return list<self>
     */
    public static function hard(array $violations): array
    {
        return array_values(array_filter($violations, fn (self $violation): bool => $violation->isHard()));
    }

    /**
     * @param  list<self>  $violations
     * @return list<string>
     */
    public static function values(array $violations): array
    {
        return array_map(fn (self $violation): string => $violation->value, $violations);
    }
}
