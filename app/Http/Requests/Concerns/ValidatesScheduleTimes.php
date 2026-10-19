<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Level;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Times (local, "H:i") and positions shared by templates and events (FR-008):
 * arrival ≤ start < end, teardown in minutes, each role at most once.
 */
trait ValidatesScheduleTimes
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function scheduleRules(): array
    {
        return [
            'arrival_time' => ['required', 'date_format:H:i', 'before_or_equal:start_time'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'teardown_minutes' => ['required', 'integer', 'between:0,480'],
            'location' => ['required', 'string', 'max:160'],
            'positions' => ['required', 'array', 'min:1'],
            'positions.*.team_role_id' => ['required', 'integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'positions.*.quantity' => ['required', 'integer', 'between:1,20'],
            'positions.*.min_level' => ['nullable', Rule::enum(Level::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function scheduleMessages(): array
    {
        return [
            'arrival_time.before_or_equal' => 'A hora de chegada da equipa não pode ser depois do início.',
            'end_time.after' => 'O fim tem de ser depois do início.',
            'positions.required' => 'Indique pelo menos uma posição.',
            'positions.*.team_role_id.distinct' => 'Cada função só pode aparecer uma vez.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function scheduleAttributes(): array
    {
        return [
            'arrival_time' => 'chegada da equipa',
            'start_time' => 'início',
            'end_time' => 'fim',
            'teardown_minutes' => 'desmontagem',
            'location' => 'local',
            'positions.*.quantity' => 'quantidade',
        ];
    }
}
