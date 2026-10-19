<?php

namespace App\Console\Commands;

use App\Actions\Registration\DeleteUserAccount;
use App\Actions\Registration\RejectVolunteer;
use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes rejected registrations once they can no longer be reversed (FR-003a). Only the
 * phone hash and rejection date stay, in `rejected_registrations`.
 */
#[Signature('registrations:purge-rejected')]
#[Description('Delete rejected registrations older than 30 days')]
class PurgeRejectedRegistrations extends Command
{
    public function handle(DeleteUserAccount $deleteAccount): int
    {
        $volunteers = Volunteer::query()
            ->where('status', VolunteerStatus::Rejected)
            ->where('reviewed_at', '<', now()->subDays(RejectVolunteer::REVERSIBLE_DAYS))
            ->with('user')
            ->get();

        $volunteers->each(fn (Volunteer $volunteer) => DB::transaction(function () use ($volunteer, $deleteAccount): void {
            $user = $volunteer->user;

            $volunteer->delete();

            if ($user !== null) {
                $deleteAccount->handle($user);
            }
        }));

        $this->info("{$volunteers->count()} cadastros rejeitados apagados.");

        return self::SUCCESS;
    }
}
