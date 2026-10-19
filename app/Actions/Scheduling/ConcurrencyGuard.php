<?php

namespace App\Actions\Scheduling;

use App\Models\Assignment;
use Illuminate\Validation\ValidationException;

/**
 * Optimistic concurrency for assignment edits (FR-013): a change based on an outdated
 * version is refused with the name of whoever changed it.
 */
class ConcurrencyGuard
{
    public function ensureCurrent(Assignment $assignment, ?int $lockVersion): void
    {
        if ($lockVersion === null || $lockVersion === $assignment->lock_version) {
            return;
        }

        $who = $assignment->updater()->value('name') ?? 'outro coordenador';

        throw ValidationException::withMessages([
            'lock_version' => "Alterado por {$who} — recarregar",
        ]);
    }
}
