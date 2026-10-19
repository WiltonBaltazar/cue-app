<?php

namespace App\Console\Commands;

use App\Actions\Events\GenerateOccurrences;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:generate')]
#[Description('Generate recurring activity occurrences for the configured horizon')]
class GenerateOccurrencesCommand extends Command
{
    public function handle(GenerateOccurrences $generateOccurrences): int
    {
        $created = $generateOccurrences->handle();

        $this->info("{$created} ocorrências criadas.");

        return self::SUCCESS;
    }
}
