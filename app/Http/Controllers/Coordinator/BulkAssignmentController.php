<?php

namespace App\Http\Controllers\Coordinator;

use App\Actions\Scheduling\BulkEditAssignments;
use App\Enums\BulkAssignmentAction;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Bulk actions on the assignments selected in the schedule editor (FR-013).
 */
class BulkAssignmentController extends Controller
{
    public function __invoke(Request $request, Schedule $schedule, #[CurrentUser] User $coordinator, BulkEditAssignments $bulk): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $request->validate([
            'action' => ['required', Rule::enum(BulkAssignmentAction::class)],
            'assignments' => ['required', 'array', 'min:1', 'max:500'],
            'assignments.*.id' => ['required', 'integer', 'distinct'],
            'assignments.*.lock_version' => ['required', 'integer'],
        ], [
            'assignments.required' => 'Seleccione pelo menos uma atribuição.',
        ]);

        // Assignment id => lock version the editor last saw.
        $versions = [];
        foreach (array_keys($request->array('assignments')) as $index) {
            $versions[$request->integer("assignments.{$index}.id")] = $request->integer("assignments.{$index}.lock_version");
        }

        $action = $request->enum('action', BulkAssignmentAction::class) ?? abort(422);

        $message = match ($action) {
            BulkAssignmentAction::Remove => $this->removed($bulk->remove($schedule, $versions), $schedule),
            BulkAssignmentAction::Replace => $this->replaced($bulk->autoReplace($schedule, $versions, $coordinator)),
            BulkAssignmentAction::Lock => $this->count($bulk->setLocked($schedule, $versions, true, $coordinator), 'fixada', 'fixadas').' "Gerar de novo" mantém-nas.',
            BulkAssignmentAction::Unlock => $this->count($bulk->setLocked($schedule, $versions, false, $coordinator), 'desafixada', 'desafixadas'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function removed(int $count, Schedule $schedule): string
    {
        return $this->count($count, 'retirada', 'retiradas').($schedule->isPublished() ? ' Os voluntários foram avisados.' : '');
    }

    /**
     * @param  array{replaced: int, missing: int}  $result
     */
    private function replaced(array $result): string
    {
        $text = $result['replaced'] === 1 ? '1 substituição feita.' : "{$result['replaced']} substituições feitas.";

        return $result['missing'] === 0 ? $text : "{$text} {$result['missing']} ficaram por preencher: não há mais ninguém disponível.";
    }

    private function count(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 atribuição {$singular}." : "{$count} atribuições {$plural}.";
    }
}
