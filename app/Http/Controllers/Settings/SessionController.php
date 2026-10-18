<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\EndOtherSessions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets each user see and end their sessions on other devices (FR-032).
 */
class SessionController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $currentId = $request->session()->getId();

        $sessions = $this->sessionsQuery($user)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'device' => $this->describeDevice((string) $session->user_agent),
                'ip_address' => $session->ip_address,
                'last_active' => Carbon::createFromTimestamp((int) $session->last_activity)->diffForHumans(),
                'is_current' => $session->id === $currentId,
            ])
            ->all();

        return Inertia::render('settings/sessions', [
            'sessions' => $sessions,
        ]);
    }

    public function destroy(Request $request, #[CurrentUser] User $user, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 422, 'Para terminar esta sessão use "Sair".');

        $this->sessionsQuery($user)->where('id', $session)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessão terminada.']);

        return back();
    }

    public function destroyOthers(Request $request, #[CurrentUser] User $user, EndOtherSessions $endOtherSessions): RedirectResponse
    {
        $endOtherSessions->handle($user, $request->session()->getId());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sessões noutros dispositivos terminadas.']);

        return back();
    }

    /**
     * Only ever touches the signed-in user's own rows.
     */
    private function sessionsQuery(User $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier());
    }

    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            Str::contains($userAgent, 'Edg') => 'Edge',
            Str::contains($userAgent, ['Chrome', 'CriOS']) => 'Chrome',
            Str::contains($userAgent, ['Firefox', 'FxiOS']) => 'Firefox',
            Str::contains($userAgent, 'Safari') => 'Safari',
            default => 'Navegador',
        };

        $platform = match (true) {
            Str::contains($userAgent, ['iPhone', 'iPad']) => 'iPhone/iPad',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, 'Mac OS') => 'Mac',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => 'dispositivo desconhecido',
        };

        return "{$browser} em {$platform}";
    }
}
