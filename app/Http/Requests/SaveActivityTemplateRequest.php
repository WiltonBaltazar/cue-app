<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesScheduleTimes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recurring activity template (FR-008).
 */
class SaveActivityTemplateRequest extends FormRequest
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
            'weekday' => ['required', 'integer', 'between:1,7'],
            'interval_weeks' => ['required', 'integer', 'between:1,4'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'is_active' => ['required', 'boolean'],
            ...$this->scheduleRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->scheduleMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'weekday' => 'dia da semana',
            'interval_weeks' => 'repetição',
            'starts_on' => 'a partir de',
            ...$this->scheduleAttributes(),
        ];
    }
}
