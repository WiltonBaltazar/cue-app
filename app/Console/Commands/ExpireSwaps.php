<?php

namespace App\Console\Commands;

use App\Enums\SwapStatus;
use App\Models\SwapRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('swaps:expire')]
#[Description('Expire swap requests whose event has started')]
class ExpireSwaps extends Command
{
    public function handle(): int
    {
        $expired = 0;

        SwapRequest::query()
            ->active()
            ->whereHas('assignment.event', fn ($query) => $query->where('starts_at', '<=', now()))
            ->each(function (SwapRequest $swap) use (&$expired): void {
                $swap->status = SwapStatus::Expired;
                $swap->save();
                $expired++;
            });

        $this->info("{$expired} pedidos de troca expirados.");

        return self::SUCCESS;
    }
}
