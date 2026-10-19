<?php

namespace App\Http\Controllers\AppApi;

use App\Domain\Scheduling\SwapEligibility;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Colleagues who could take one of my shifts, for a directed swap (contracts/app-api.md).
 * Short names and level only.
 */
class SwapCandidateController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Assignment $assignment, SwapEligibility $eligibility): JsonResponse
    {
        abort_unless($assignment->volunteer_id === $user->volunteer?->id, 403);

        $roleId = $assignment->position()->value('team_role_id');

        return response()->json([
            'data' => $eligibility->eligibleColleagues($assignment)
                ->map(fn (Volunteer $volunteer) => [
                    'volunteerId' => $volunteer->id,
                    'displayName' => $volunteer->shortName(),
                    'level' => $volunteer->skills->firstWhere('team_role_id', $roleId)?->level->label(),
                ])
                ->values(),
        ]);
    }
}
