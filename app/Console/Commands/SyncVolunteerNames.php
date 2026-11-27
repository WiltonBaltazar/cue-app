<?php

namespace App\Console\Commands;

use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off repair: account renames made in settings used to stay on the login account only,
 * so coordinators kept seeing the old name. Copies the account name onto the volunteer record.
 */
#[Signature('volunteers:sync-names {--dry-run : List the changes without saving them}')]
#[Description('Copy each account name onto its volunteer record where they differ')]
class SyncVolunteerNames extends Command
{
    public function handle(): int
    {
        $volunteers = Volunteer::query()
            ->with('user')
            ->whereNot('status', VolunteerStatus::Anonymized)
            ->whereHas('user')
            ->get()
            ->filter(fn (Volunteer $volunteer) => trim($volunteer->user->name) !== '' && $volunteer->user->name !== $volunteer->full_name);

        foreach ($volunteers as $volunteer) {
            $this->line("#{$volunteer->id}: {$volunteer->full_name} → {$volunteer->user->name}");

            if (! $this->option('dry-run')) {
                $volunteer->update(['full_name' => $volunteer->user->name]);
            }
        }

        $this->info($this->option('dry-run')
            ? "{$volunteers->count()} nomes por actualizar (nada foi guardado)."
            : "{$volunteers->count()} nomes actualizados.");

        return self::SUCCESS;
    }
}
