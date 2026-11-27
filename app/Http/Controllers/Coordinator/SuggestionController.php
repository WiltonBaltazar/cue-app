<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\SuggestionStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The suggestions volunteers leave in their registration ("Sugestões para a equipa"), for
 * coordinators to follow up: in progress, done or archived, with a note for the team.
 */
class SuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Volunteer::class);

        $status = SuggestionStatus::tryFrom($request->string('estado')->toString()) ?? SuggestionStatus::New;

        $counts = Volunteer::query()
            ->whereNotNull('suggestion_status')
            ->selectRaw('suggestion_status, count(*) as total')
            ->groupBy('suggestion_status')
            ->pluck('total', 'suggestion_status');

        $suggestions = Volunteer::query()
            ->where('suggestion_status', $status)
            ->with('suggestionHandler.volunteer')
            ->latest('updated_at')
            ->get();

        return Inertia::render('coord/suggestions/index', [
            'status' => $status->value,
            'statuses' => collect(SuggestionStatus::cases())->map(fn (SuggestionStatus $option) => [
                'value' => $option->value,
                'label' => $option->label(),
                'count' => (int) ($counts[$option->value] ?? 0),
            ]),
            'suggestions' => $suggestions->map(fn (Volunteer $volunteer) => [
                'volunteerId' => $volunteer->id,
                'volunteer' => $volunteer->full_name,
                'volunteerStatus' => $volunteer->status->label(),
                'text' => $volunteer->suggestions,
                'submittedAt' => $volunteer->created_at?->toIso8601String(),
                'note' => $volunteer->suggestion_note,
                'handledBy' => $volunteer->suggestionHandler?->volunteer?->full_name ?? $volunteer->suggestionHandler?->name,
                'handledAt' => $volunteer->suggestion_handled_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request, Volunteer $volunteer, #[CurrentUser] User $coordinator): RedirectResponse
    {
        Gate::authorize('review', $volunteer);
        abort_if($volunteer->suggestion_status === null, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(SuggestionStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $previous = $volunteer->suggestion_status;

        $volunteer->forceFill([
            'suggestion_status' => SuggestionStatus::from($validated['status']),
            'suggestion_note' => $validated['note'] ?? null,
            'suggestion_handled_by' => $coordinator->id,
            'suggestion_handled_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sugestão actualizada.']);

        return to_route('coord.suggestions.index', ['estado' => $previous->value]);
    }
}
