<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out everywhere except the current session (FR-032): deletes their other
 * database sessions and rotates the "remember me" token so remembered devices log out too.
 */
class EndOtherSessions
{
    public function handle(User $user, ?string $currentSessionId): int
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->when($currentSessionId, fn ($query, string $id) => $query->where('id', '!=', $id))
            ->delete();
    }
}
