<?php

namespace App\Actions\Registration;

use App\Models\Volunteer;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws a data-removal request within the 7 days (FR-028). Shifts already flagged for
 * replacement stay that way: coordinators may have found someone else.
 */
class CancelDataRemoval
{
    public function handle(Volunteer $volunteer): void
    {
        if ($volunteer->isAnonymized() || $volunteer->removal_requested_at === null) {
            throw ValidationException::withMessages(['removal' => 'Não há nenhum pedido de remoção para cancelar.']);
        }

        $volunteer->removal_requested_at = null;
        $volunteer->removal_scheduled_for = null;
        $volunteer->save();
    }
}
