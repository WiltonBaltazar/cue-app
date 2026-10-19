<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ConsentVersion;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editing the declaration publishes a new version; every volunteer accepts it on their
 * next visit (FR-002). Audited through ConsentVersion's activity log.
 */
class ConsentController extends Controller
{
    public function edit(): Response
    {
        $current = ConsentVersion::current();

        return Inertia::render('coord/consent', [
            'declaration' => [
                'version' => $current->version,
                'body' => $current->body,
                'publishedAt' => $current->published_at->toIso8601String(),
            ],
            'volunteerCount' => Volunteer::query()->whereNull('anonymized_at')->count(),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $coordinator): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:50', 'max:10000'],
        ], [
            'body.min' => 'A declaração parece demasiado curta.',
        ]);

        DB::transaction(function () use ($validated, $coordinator): void {
            ConsentVersion::query()->create([
                'version' => ConsentVersion::currentNumber() + 1,
                'body' => $validated['body'],
                'published_by' => $coordinator->id,
                'published_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nova versão publicada. Os voluntários vão aceitá-la no próximo acesso.']);

        return to_route('coord.consent.edit');
    }
}
