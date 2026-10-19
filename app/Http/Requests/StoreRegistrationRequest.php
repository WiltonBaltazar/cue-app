<?php

namespace App\Http\Requests;

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
use App\Enums\TrainingAvailability;
use App\Models\ConsentVersion;
use App\Models\User;
use App\Models\Volunteer;
use App\Rules\Turnstile;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Public registration form, sections I–VII plus the declaration (FR-001, FR-002, FR-002a).
 */
class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_e164' => Phone::normalize($this->string('phone')->toString()),
            'guardian_phone_e164' => Phone::normalize($this->string('guardian_phone')->toString()),
            'whatsapp_phone_e164' => Phone::normalize($this->string('whatsapp_phone')->toString()),
            'is_minor' => $this->ageFromBirthDate() !== null && $this->ageFromBirthDate() < Volunteer::ADULT_AGE,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::now(config('app.display_timezone'));
        $isMinor = $this->boolean('is_minor');

        return [
            // I. Identificação
            'full_name' => ['required', 'string', 'min:3', 'max:160'],
            'nickname' => ['nullable', 'string', 'max:80'],
            'birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.$today->subYears(Volunteer::MINIMUM_AGE)->toDateString(),
                'after:'.$today->subYears(100)->toDateString(),
            ],
            'neighbourhood' => ['nullable', 'string', 'max:120'],

            // II. Contactos
            'phone' => ['required', 'string', 'max:30'],
            'phone_has_whatsapp' => ['boolean'],
            'email' => ['nullable', 'string', 'email', 'max:190', Rule::unique(User::class, 'email')],
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
            'social_media' => ['array', 'max:10'],
            'social_media.*.network' => ['required', 'string', Rule::in(Volunteer::SOCIAL_NETWORKS)],
            'social_media.*.handle' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'guardian_name' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:160'],
            'guardian_phone' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:30'],

            // III. Escolaridade e formação
            'education_level' => ['nullable', 'string', 'max:120'],
            'training' => ['nullable', 'string', 'max:255'],

            // III. Áreas de interesse e serviço
            'skills' => [Rule::requiredIf(blank($this->input('other_area'))), 'array'],
            'skills.*.team_role_id' => ['required', 'integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'preferred_role_id' => ['nullable', 'integer', Rule::in($this->collect('skills')->pluck('team_role_id')->all())],
            'other_area' => ['nullable', 'string', 'max:120'],

            // IV. Experiência e competências
            'no_experience' => ['boolean'],
            'experience_role_ids' => ['array'],
            'experience_role_ids.*' => ['integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'other_experience' => ['nullable', 'string', 'max:120'],
            'experience_description' => ['nullable', 'string', 'max:2000'],
            'level' => ['required', new Enum(Level::class)],

            // V. Disponibilidade
            'availability' => [Rule::requiredIf(! $this->boolean('variable_hours') && ! $this->boolean('other_days_by_arrangement')), 'array'],
            'availability.*.weekday' => ['required', 'integer', 'between:1,7'],
            'availability.*.period' => ['required', new Enum(Period::class)],
            'other_days_by_arrangement' => ['boolean'],
            'max_frequency' => ['required', new Enum(Frequency::class)],
            'frequency_note' => ['nullable', 'string', 'max:255'],
            'variable_hours' => ['boolean'],
            'training_availability' => ['required', new Enum(TrainingAvailability::class)],
            'commitments' => ['nullable', 'string', 'max:2000'],

            // VI. Motivação, expectativas e sonhos
            'motivations' => ['nullable', 'string', 'max:2000'],
            'expectations' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'string', 'max:2000'],

            // VII. Comentários e sugestões
            'comments' => ['nullable', 'string', 'max:2000'],
            'suggestions' => ['nullable', 'string', 'max:2000'],

            // Declaração
            'consent_accepted' => ['accepted'],
            'consent_version' => ['required', 'integer', Rule::in([ConsentVersion::currentNumber()])],

            'cf-turnstile-response' => [new Turnstile($this->ip())],
        ];
    }

    /**
     * Phone checks need the normalised number, so they run after the basic rules.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $phone = $this->input('phone_e164');

                if (! is_string($phone)) {
                    $validator->errors()->add('phone', 'Indique um número de telefone válido, por exemplo 84 123 4567.');
                } elseif (User::query()->where('phone', $phone)->exists()) {
                    $validator->errors()->add('phone', 'Já existe uma conta com este número. Entre ou recupere a palavra-passe.');
                }

                if (! $this->boolean('phone_has_whatsapp') && filled($this->input('whatsapp_phone')) && ! is_string($this->input('whatsapp_phone_e164'))) {
                    $validator->errors()->add('whatsapp_phone', 'Indique um número de WhatsApp válido, por exemplo 84 123 4567.');
                }

                if (! $this->boolean('no_experience') && blank($this->input('experience_role_ids')) && blank($this->input('other_experience'))) {
                    $validator->errors()->add('experience_role_ids', 'Indique as áreas em que tem experiência, ou marque que ainda não tem experiência.');
                }

                if ($this->boolean('is_minor') && filled($this->input('guardian_phone')) && ! is_string($this->input('guardian_phone_e164'))) {
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
            'birth_date.before_or_equal' => 'É preciso ter pelo menos 14 anos para se registar.',
            'consent_accepted.accepted' => 'Para enviar o cadastro tem de aceitar a declaração.',
            'consent_version.in' => 'A declaração foi actualizada entretanto. Leia a nova versão e aceite novamente.',
            'skills.required' => 'Escolha pelo menos uma área de serviço.',
            'availability.required' => 'Indique pelo menos um dia e período em que está disponível, ou que tem horários variáveis.',
            'training_availability.required' => 'Indique se pode participar em reuniões e formações.',
            'guardian_name.required' => 'Menores de 18 anos têm de indicar o nome do encarregado.',
            'guardian_phone.required' => 'Menores de 18 anos têm de indicar o telefone do encarregado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nome completo',
            'nickname' => 'alcunha',
            'whatsapp_phone' => 'número de WhatsApp',
            'social_media.*.handle' => 'perfil',
            'other_area' => 'outra área',
            'other_experience' => 'outra experiência',
            'experience_description' => 'descrição da experiência',
            'frequency_note' => 'outra situação',
            'expectations' => 'expectativas',
            'goals' => 'sonhos e objectivos',
            'suggestions' => 'sugestões',
            'birth_date' => 'data de nascimento',
            'neighbourhood' => 'bairro',
            'phone' => 'telefone',
            'email' => 'e-mail',
            'password' => 'palavra-passe',
            'guardian_name' => 'nome do encarregado',
            'guardian_phone' => 'telefone do encarregado',
            'education_level' => 'escolaridade',
            'training' => 'formação',
            'level' => 'nível de experiência',
            'max_frequency' => 'frequência',
            'commitments' => 'compromissos',
            'motivations' => 'motivações',
            'comments' => 'comentários',
        ];
    }

    private function ageFromBirthDate(): ?int
    {
        $value = $this->string('birth_date')->toString();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return (int) CarbonImmutable::parse($value)->diffInYears(CarbonImmutable::now(config('app.display_timezone')));
    }
}
