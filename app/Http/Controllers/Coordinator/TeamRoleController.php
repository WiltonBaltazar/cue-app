<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\TeamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue of team roles ("Funções", FR-007). Roles are deactivated, never deleted,
 * so history and volunteer skills keep their meaning.
 */
class TeamRoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', TeamRole::class);

        return Inertia::render('coord/team-roles/index', [
            'teamRoles' => TeamRole::query()
                ->ordered()
                ->withCount('volunteerSkills')
                ->get()
                ->map(fn (TeamRole $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'isActive' => $role->is_active,
                    'volunteerCount' => $role->volunteer_skills_count,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TeamRole::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('team_roles', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => 'nome']);

        TeamRole::query()->create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
            'sort_order' => (int) TeamRole::query()->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Função adicionada.']);

        return to_route('coord.team-roles.index');
    }

    public function update(Request $request, TeamRole $teamRole): RedirectResponse
    {
        Gate::authorize('update', $teamRole);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('team_roles', 'name')->ignore($teamRole)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ], [], ['name' => 'nome']);

        $teamRole->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Função actualizada.']);

        return to_route('coord.team-roles.index');
    }
}
