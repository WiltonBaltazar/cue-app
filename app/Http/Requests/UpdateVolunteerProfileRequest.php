<?php

namespace App\Http\Requests;

use App\Enums\Frequency;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
use App\Enums\VolunteerStatus;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The volunteer's own profile data (FR-005). Name and birth date are only editable
 * while the registration is under review; levels are set by coordinators.
 */
class UpdateVolunteerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $volunteer = $this->volunteer();

        return $volunteer !== null && ($this->user()?->can('update', $volunteer) ?? false);
    }

    public function volunteer(): ?Volunteer
    {
        $user = $this->user();

        return $user instanceof User ? $user->volunteer : null;
    }

    public function canEditIdentity(): bool
    {
        return in_array($this->volunteer()?->status, [VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_phone_e164' => Phone::normalize($this->string('guardian_phone')->toString()),
            'whatsapp_phone_e164' => Phone::normalize($this->string('whatsapp_phone')->toString()),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isMinor = (bool) $this->volunteer()?->is_minor;
        $today = CarbonImmutable::now(config('app.display_timezone'));

        return [
            'full_name' => [Rule::excludeIf(! $this->canEditIdentity()), 'required', 'string', 'min:3', 'max:160'],
            'birth_date' => [
                Rule::excludeIf(! $this->canEditIdentity()),
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.$today->subYears(Volunteer::MINIMUM_AGE)->toDateString(),
            ],
            'nickname' => ['nullable', 'string', 'max:80'],
            'neighbourhood' => ['nullable', 'string', 'max:120'],
            'phone_has_whatsapp' => ['boolean'],
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
            'social_media' => ['array', 'max:10'],
            'social_media.*.network' => ['required', 'string', Rule::in(Volunteer::SOCIAL_NETWORKS)],
            'social_media.*.handle' => ['required', 'string', 'max:120'],
            'guardian_name' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:160'],
            'guardian_phone' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:30'],
            'education_level' => ['nullable', 'string', 'max:120'],
            'training' => ['nullable', 'string', 'max:255'],
            'skills' => [Rule::requiredIf(blank($this->input('other_area'))), 'array'],
            'skills.*.team_role_id' => ['required', 'integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'preferred_role_id' => ['nullable', 'integer', Rule::in($this->collect('skills')->pluck('team_role_id')->all())],
            'other_area' => ['nullable', 'string', 'max:120'],
            'no_experience' => ['boolean'],
            'experience_role_ids' => ['array'],
            'experience_role_ids.*' => ['integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'other_experience' => ['nullable', 'string', 'max:120'],
            'experience_description' => ['nullable', 'string', 'max:2000'],
            'can_support_others' => ['boolean'],
            'availability' => [Rule::requiredIf(! $this->boolean('variable_hours') && ! $this->boolean('other_days_by_arrangement')), 'array'],
            'availability.*.weekday' => ['required', 'integer', 'between:1,7'],
            'availability.*.period' => ['required', new Enum(Period::class)],
            'other_days_by_arrangement' => ['boolean'],
            'max_frequency' => ['required', new Enum(Frequency::class)],
            'frequency_note' => ['nullable', 'string', 'max:255'],
            'variable_hours' => ['boolean'],
            'training_availability' => ['required', new Enum(TrainingAvailability::class)],
            'commitments' => ['nullable', 'string', 'max:2000'],
            'motivations' => ['nullable', 'string', 'max:2000'],
            'expectations' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'suggestions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('phone_has_whatsapp') && filled($this->input('whatsapp_phone')) && ! is_string($this->input('whatsapp_phone_e164'))) {
                    $validator->errors()->add('whatsapp_phone', 'Indique um número de WhatsApp válido, por exemplo 84 123 4567.');
                }

                if (! $this->boolean('no_experience') && blank($this->input('experience_role_ids')) && blank($this->input('other_experience'))) {
                    $validator->errors()->add('experience_role_ids', 'Indique as áreas em que tem experiência, ou marque que ainda não tem experiência.');
                }

                if (filled($this->input('guardian_phone')) && ! is_string($this->input('guardian_phone_e164'))) {
                    $validator->errors()->add('guardian_phone', 'Indique um número de telefone válido para o encarregado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => 'É preciso ter pelo menos 14 anos.',
            'skills.required' => 'Escolha pelo menos uma área de serviço.',
            'availability.required' => 'Indique pelo menos um dia e período em que está disponível, ou que tem horários variáveis.',
            'training_availability.required' => 'Indique se pode participar em reuniões e formações.',
        ];
    }
}
