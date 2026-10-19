<?php

namespace App\Actions\Registration;

use App\Actions\Swaps\ReplacementFlagger;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Volunteer;
use App\Notifications\DataRemovalRequested;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A volunteer asks for their data to be removed (FR-028). Anonymisation is scheduled for
 * 7 days later and can be cancelled until then; meanwhile they are no longer scheduled,
 * their upcoming published shifts become "Por substituir" and coordinators are told.
 */
class RequestDataRemoval
{
    public const GRACE_DAYS = 7;

    public function __construct(private ReplacementFlagger $flagger) {}

    /**
     * @return int published shifts flagged for replacement
     */
    public function handle(Volunteer $volunteer): int
    {
        if ($volunteer->isAnonymized() || $volunteer->removal_requested_at !== null) {
            throw ValidationException::withMessages(['removal' => 'Já existe um pedido de remoção.']);
        }

        return DB::transaction(function () use ($volunteer): int {
            $volunteer->removal_requested_at = now();
            $volunteer->removal_scheduled_for = now()->addDays(self::GRACE_DAYS);
            $volunteer->save();

            // Draft schedules are only plans: free those places for someone else.
            $volunteer->assignments()
                ->whereHas('schedule', fn ($query) => $query->where('status', ScheduleStatus::Draft))
                ->whereHas('event', fn ($query) => $query->where('date', '>=', LocalTime::today()->toDateString()))
                ->delete();

            $flagged = $this->flagger->flag($volunteer, LocalTime::today());

            Notification::send(
                User::query()->where('role', UserRole::Coordinator)->get(),
                new DataRemovalRequested($volunteer->shortName(), $flagged),
            );

            return $flagged;
        });
    }
}
