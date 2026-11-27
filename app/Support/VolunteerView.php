<?php

namespace App\Support;

use App\Models\User;
use App\Models\Volunteer;

/**
 * "Ver como voluntário": a coordinator opens the volunteer app exactly as one volunteer sees it,
 * read-only. The chosen volunteer lives in the session; opening the management area ends it.
 */
final class VolunteerView
{
    private const SESSION_KEY = 'volunteer_view';

    public static function enter(Volunteer $volunteer): void
    {
        session()->put(self::SESSION_KEY, $volunteer->id);
    }

    public static function leave(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * The volunteer being viewed, if this coordinator is in the volunteer view.
     */
    public static function target(?User $user): ?Volunteer
    {
        $id = session()->get(self::SESSION_KEY);

        if ($user === null || ! $user->isCoordinator() || ! is_int($id)) {
            return null;
        }

        $volunteer = Volunteer::query()->find($id);

        return $volunteer === null || $volunteer->isAnonymized() ? null : $volunteer;
    }
}
