<?php

namespace App\Console\Commands;

use App\Actions\Registration\AnonymizeVolunteer;
use App\Models\Volunteer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Anonymises volunteers whose 7-day removal window has ended (FR-028). No approval needed.
 */
#[Signature('volunteers:process-removals')]
#[Description('Anonymise volunteers whose data-removal request is due')]
class ProcessScheduledRemovals extends Command
{
    public function handle(AnonymizeVolunteer $anonymize): int
    {
        $volunteers = Volunteer::query()
            ->whereNull('anonymized_at')
            ->whereNotNull('removal_scheduled_for')
            ->where('removal_scheduled_for', '<=', now())
            ->with('user')
            ->get();

        $volunteers->each(fn (Volunteer $volunteer) => $anonymize->handle($volunteer));

        $this->info("{$volunteers->count()} voluntários anonimizados.");

        return self::SUCCESS;
    }
}
