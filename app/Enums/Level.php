<?php

namespace App\Enums;

/**
 * Skill level of a volunteer in one team role, ordered beginner < intermediate < advanced < mentor.
 */
enum Level: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Mentor = 'mentor';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Iniciante',
            self::Intermediate => 'Intermédio',
            self::Advanced => 'Avançado',
            self::Mentor => 'Mentor',
        };
    }

    /**
     * How the registration form asks for the level (question 9): "mentor" is described.
     */
    public function selfAssessmentLabel(): string
    {
        return $this === self::Mentor ? 'Posso apoiar outros membros e orientar a sua aprendizagem' : $this->label();
    }

    public function rank(): int
    {
        return match ($this) {
            self::Beginner => 1,
            self::Intermediate => 2,
            self::Advanced => 3,
            self::Mentor => 4,
        };
    }

    /**
     * Whether this level satisfies a position's minimum level (null = any level).
     */
    public function meets(?self $minimum): bool
    {
        return $minimum === null || $this->rank() >= $minimum->rank();
    }
}
