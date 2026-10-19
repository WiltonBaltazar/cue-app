<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduleTimes;
use App\Support\LocalTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Special event, or one occurrence of a recurring activity (FR-008, FR-009).
 */
class SaveEventRequest extends FormRequest
{
    use ValidatesScheduleTimes;

    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.LocalTime::today()->toDateString()],
            ...$this->scheduleRules(),
        ];
    }

    /**
     * Validated input in the shape SaveEvent expects.
     *
     * @return array{name: string, date: string, arrival_time: string, start_time: string, end_time: string, teardown_minutes: int, location: string, positions: list<array{team_role_id: int, quantity: int, min_level: string|null}>}
     */
    public function eventData(): array
    {
        $this->validated();

        return [
            'name' => $this->string('name')->toString(),
            'date' => $this->string('date')->toString(),
            'arrival_time' => $this->string('arrival_time')->toString(),
            'start_time' => $this->string('start_time')->toString(),
            'end_time' => $this->string('end_time')->toString(),
            'teardown_minutes' => $this->integer('teardown_minutes'),
            'location' => $this->string('location')->toString(),
            'positions' => array_values($this->collect('positions')
                ->map(fn (mixed $position): array => [
                    'team_role_id' => (int) data_get($position, 'team_role_id'),
                    'quantity' => (int) data_get($position, 'quantity'),
                    'min_level' => filled(data_get($position, 'min_level')) ? (string) data_get($position, 'min_level') : null,
                ])
                ->all()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Não é possível criar ou mudar eventos para datas passadas.',
            ...$this->scheduleMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'date' => 'data', ...$this->scheduleAttributes()];
    }
}
