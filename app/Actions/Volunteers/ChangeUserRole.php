<?php

namespace App\Actions\Volunteers;

use App\Enums\UserRole;
use App\Enums\VolunteerStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives or takes away the coordinator role. The change is audited on the user (`role`
 * is an audited attribute), and the app always keeps at least one coordinator.
 */
class ChangeUserRole
{
    public function handle(User $user, UserRole $role): void
    {
        if ($user->role === $role) {
            return;
        }

        $status = $user->volunteer?->status;

        if ($role === UserRole::Coordinator && $status !== null && ! in_array($status, [VolunteerStatus::Active, VolunteerStatus::Inactive], true)) {
            throw ValidationException::withMessages([
                'role' => 'Só voluntários com o cadastro aprovado podem ser coordenadores.',
            ]);
        }

        DB::transaction(function () use ($user, $role): void {
            if ($user->role === UserRole::Coordinator) {
                // Lock the rows themselves: PostgreSQL refuses FOR UPDATE with COUNT(*).
                $coordinators = User::query()->where('role', UserRole::Coordinator)->lockForUpdate()->pluck('id')->count();

                if ($coordinators <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Tem de haver pelo menos um coordenador. Dê o papel a outra pessoa antes de o retirar a esta.',
                    ]);
                }
            }

            $user->role = $role;
            $user->save();
        });
    }
}
