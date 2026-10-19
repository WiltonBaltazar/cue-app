<?php

namespace App\Actions\Registration;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a login and everything tied to it that a foreign key does not clean up:
 * sessions, push subscriptions, in-app notifications and reset tokens (FR-003a, FR-028).
 * Audit rows naming the user by ID are kept untouched (FR-029).
 */
class DeleteUserAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($user->email !== null) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            $user->pushSubscriptions()->delete();
            $user->notifications()->delete();
            $user->delete();
        });
    }
}
