<?php

namespace App\Actions\Auth;

use App\Models\PasswordSetupLink;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Support\Str;

/**
 * A single-use, 24-hour link a coordinator hands to a volunteer in person so they can
 * set a new password without e-mail (FR-006). Issuing a new link replaces the old one.
 */
class IssueResetLink
{
    public const VALID_HOURS = 24;

    /**
     * @return string the full URL, shown to the coordinator once
     */
    public function handle(Volunteer $volunteer, User $coordinator): string
    {
        $user = $volunteer->user;
        abort_if($user === null, 404);

        $token = Str::random(64);

        PasswordSetupLink::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'token_hash' => PasswordSetupLink::hashToken($token),
                'issued_by' => $coordinator->id,
                'expires_at' => now()->addHours(self::VALID_HOURS),
            ],
        );

        activity('audit')
            ->performedOn($volunteer)
            ->causedBy($coordinator)
            ->event('issued_reset_link')
            ->log('issued_reset_link');

        return route('password-setup.show', $token);
    }
}
