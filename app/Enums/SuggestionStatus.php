<?php

namespace App\Enums;

/**
 * Where a coordinator is with a volunteer's suggestion for the team.
 */
enum SuggestionStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nova',
            self::InProgress => 'Em curso',
            self::Done => 'Feita',
            self::Archived => 'Arquivada',
        };
    }
}
