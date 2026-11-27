<?php

namespace App\Support;

use App\Models\User;

/**
 * A coordinator who also serves can switch to the volunteer app ("Ver como voluntário").
 * The choice lives in the session and ends as soon as they open the management area.
 */
final class VolunteerView
{
    private const SESSION_KEY = 'volunteer_view';

    public static function active(?User $user): bool
    {
        return $user !== null && $user->isCoordinator() && session()->get(self::SESSION_KEY, false) === true;
    }

    public static function enter(): void
    {
        session()->put(self::SESSION_KEY, true);
    }

    public static function leave(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
