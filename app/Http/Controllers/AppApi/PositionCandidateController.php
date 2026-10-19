<?php

namespace App\Http\Controllers\AppApi;

use App\Actions\Scheduling\AssignVolunteer;
use App\Domain\Scheduling\CandidateFinder;
use App\Domain\Scheduling\Violation;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Position;
use App\Models\Volunteer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Candidate ranking and dry-run checks for the schedule editor (contracts/app-api.md).
 */
class PositionCandidateController extends Controller
{
    public function index(Request $request, Position $position, CandidateFinder $finder): JsonResponse
    {
        Gate::authorize('create', Assignment::class);

        $replacing = $request->filled('substituir')
            ? Assignment::query()->where('position_id', $position->id)->findOrFail($request->integer('substituir'))
            : null;

        return response()->json(['data' => $finder->for($position->load('event'), $replacing)]);
    }

    public function check(Request $request, AssignVolunteer $assign): JsonResponse
    {
        Gate::authorize('create', Assignment::class);

        $validated = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'volunteer_id' => ['required', 'integer', 'exists:volunteers,id'],
        ]);

        $violations = $assign->violations(
            Position::query()->with('event')->whereKey($validated['position_id'])->firstOrFail(),
            Volunteer::query()->whereKey($validated['volunteer_id'])->firstOrFail(),
        );

        return response()->json([
            'ok' => $violations === [],
            'violations' => Violation::values($violations),
            'message' => implode('; ', array_map(fn (Violation $violation) => $violation->message(), $violations)),
        ]);
    }
}
