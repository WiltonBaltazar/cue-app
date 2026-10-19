<?php

namespace App\Console\Commands;

use App\Actions\Volunteers\ResumeVolunteer;
use App\Enums\VolunteerStatus;
use App\Models\Volunteer;
use App\Support\LocalTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ends pauses whose return date has arrived (FR-005a). Nobody is ever paused automatically.
 */
#[Signature('volunteers:resume-paused')]
#[Description('Resume volunteers whose pause return date has arrived')]
class ResumePausedVolunteers extends Command
{
    public function handle(ResumeVolunteer $resume): int
    {
        $volunteers = Volunteer::query()
            ->where('status', VolunteerStatus::Inactive)
            ->whereNotNull('inactive_until')
            ->where('inactive_until', '<=', LocalTime::today()->toDateString())
            ->get();

        $volunteers->each(fn (Volunteer $volunteer) => $resume->handle($volunteer));

        $this->info("{$volunteers->count()} voluntários retomados.");

        return self::SUCCESS;
    }
}
