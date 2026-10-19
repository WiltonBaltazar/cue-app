<?php

namespace App\Http\Requests;

use App\Enums\Frequency;
use App\Enums\Level;
use App\Enums\Period;
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
            'birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.$today->subYears(Volunteer::MINIMUM_AGE)->toDateString(),
                'after:'.$today->subYears(100)->toDateString(),
            ],
            'neighbourhood' => ['nullable', 'string', 'max:120'],

            // II. Contactos
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'guardian_name' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:160'],
            'guardian_phone' => [Rule::requiredIf($isMinor), 'nullable', 'string', 'max:30'],

            // III. Escolaridade e formação
            'education_level' => ['nullable', 'string', 'max:120'],
            'training' => ['nullable', 'string', 'max:255'],

            // IV. Áreas de serviço
            'skills' => ['required', 'array', 'min:1'],
            'skills.*.team_role_id' => ['required', 'integer', 'distinct', Rule::exists('team_roles', 'id')->where('is_active', true)],
            'skills.*.has_experience' => ['boolean'],
            'level' => ['required', new Enum(Level::class)],
            'preferred_role_id' => ['nullable', 'integer', Rule::in($this->collect('skills')->pluck('team_role_id')->all())],
            'can_support_others' => ['boolean'],

            // V. Disponibilidade
            'availability' => ['required', 'array', 'min:1'],
            'availability.*.weekday' => ['required', 'integer', 'between:1,7'],
            'availability.*.period' => ['required', new Enum(Period::class)],
            'max_frequency' => ['required', new Enum(Frequency::class)],
            'variable_hours' => ['boolean'],
            'available_for_training' => ['boolean'],

            // VI–VII. Compromissos, motivações, comentários
            'commitments' => ['nullable', 'string', 'max:2000'],
            'motivations' => ['nullable', 'string', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:2000'],

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
            'skills.min' => 'Escolha pelo menos uma área de serviço.',
            'availability.required' => 'Indique pelo menos um dia e período em que está disponível.',
            'availability.min' => 'Indique pelo menos um dia e período em que está disponível.',
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
