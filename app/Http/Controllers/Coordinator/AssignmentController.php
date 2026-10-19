<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Scheduling\AssignVolunteer;
use App\Actions\Scheduling\RemoveAssignment;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Position;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Manual edits in the schedule editor (FR-013).
 */
class AssignmentController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $coordinator, AssignVolunteer $assign): RedirectResponse
    {
        Gate::authorize('create', Assignment::class);

        $validated = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'volunteer_id' => ['required', 'integer', 'exists:volunteers,id'],
            'override_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assign->handle(
            Position::query()->with('event')->whereKey($validated['position_id'])->firstOrFail(),
            Volunteer::query()->whereKey($validated['volunteer_id'])->firstOrFail(),
            $coordinator,
            overrideNote: $validated['override_note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atribuição guardada.']);

        return back();
    }

    public function update(Request $request, Assignment $assignment, #[CurrentUser] User $coordinator, AssignVolunteer $assign): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate([
            'volunteer_id' => ['required', 'integer', 'exists:volunteers,id'],
            'lock_version' => ['required', 'integer'],
            'override_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assign->handle(
            $assignment->position()->with('event')->firstOrFail(),
            Volunteer::query()->whereKey($validated['volunteer_id'])->firstOrFail(),
            $coordinator,
            $assignment,
            (int) $validated['lock_version'],
            $validated['override_note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atribuição alterada.']);

        return back();
    }

    public function destroy(Request $request, Assignment $assignment, RemoveAssignment $remove): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate(['lock_version' => ['required', 'integer']]);

        $remove->handle($assignment, (int) $validated['lock_version']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voluntário retirado da posição.']);

        return back();
    }
}
