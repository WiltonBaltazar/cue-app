import { Head, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { AvailabilityGrid } from '@/components/volunteer-form/availability-grid';
import {
    CheckboxField,
    RadioGroupField,
    TextAreaField,
    PhoneField,
    TextField,
} from '@/components/volunteer-form/fields';
import { RolePicker } from '@/components/volunteer-form/role-picker';
import { Turnstile } from '@/components/volunteer-form/turnstile';
import type {
    Option,
    TeamRoleOption,
    VolunteerFormFields,
} from '@/components/volunteer-form/types';
import {
    ADULT_AGE,
    ageOn,
    MINIMUM_AGE,
} from '@/components/volunteer-form/types';
import { store } from '@/routes/registration';

type Honeypot = {
    enabled: boolean;
    nameFieldName: string;
    validFromFieldName: string;
    encryptedValidFrom: string;
};

type Props = {
    declaration: { version: number; body: string };
    teamRoles: TeamRoleOption[];
    levels: Option[];
    frequencies: Option[];
    periods: Option[];
    weekdays: Option<number>[];
    honeypot: Honeypot;
    turnstileSiteKey: string | null;
};

type RegistrationForm = VolunteerFormFields & {
    full_name: string;
    birth_date: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
    level: string;
    consent_accepted: boolean;
    consent_version: number;
    'cf-turnstile-response': string;
};

const STEPS = [
    {
        title: 'Identificação',
        fields: [
            'full_name',
            'birth_date',
            'neighbourhood',
            'guardian_name',
            'guardian_phone',
        ],
    },
    {
        title: 'Contactos e conta',
        fields: ['phone', 'email', 'password', 'password_confirmation'],
    },
    {
        title: 'Escolaridade e formação',
        fields: ['education_level', 'training'],
    },
    {
        title: 'Áreas de serviço',
        fields: ['skills', 'level', 'preferred_role_id', 'can_support_others'],
    },
    {
        title: 'Disponibilidade',
        fields: [
            'availability',
            'max_frequency',
            'variable_hours',
            'available_for_training',
        ],
    },
    { title: 'Compromissos', fields: ['commitments'] },
    { title: 'Motivações e comentários', fields: ['motivations', 'comments'] },
    {
        title: 'Declaração',
        fields: [
            'consent_accepted',
            'consent_version',
            'cf-turnstile-response',
            'form',
        ],
    },
] as const;

const DRAFT_KEY = 'cue.registration-draft';

/** Only non-sensitive fields survive a reload (P1); sensitive ones stay in memory. */
const DRAFT_FIELDS = [
    'full_name',
    'phone',
    'email',
    'skills',
    'level',
    'preferred_role_id',
    'can_support_others',
    'availability',
    'max_frequency',
    'variable_hours',
    'available_for_training',
] as const;

function readDraft(): { step: number; data: Partial<RegistrationForm> } | null {
    try {
        const raw = window.sessionStorage.getItem(DRAFT_KEY);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function stepOfError(field: string): number {
    const root = field.split('.')[0];
    const index = STEPS.findIndex((step) =>
        (step.fields as readonly string[]).includes(root),
    );

    return index === -1 ? 0 : index;
}

export default function RegistrationCreate({
    declaration,
    teamRoles,
    levels,
    frequencies,
    periods,
    weekdays,
    honeypot,
    turnstileSiteKey,
}: Props) {
    const draft = useMemo(
        () => (typeof window === 'undefined' ? null : readDraft()),
        [],
    );
    const [step, setStep] = useState(draft?.step ?? 0);
    const [stepError, setStepError] = useState<string | null>(null);
    const [honeypotValue, setHoneypotValue] = useState('');

    const form = useForm<RegistrationForm>({
        full_name: '',
        birth_date: '',
        neighbourhood: '',
        guardian_name: '',
        guardian_phone: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        education_level: '',
        training: '',
        skills: [],
        level: '',
        preferred_role_id: null,
        can_support_others: false,
        availability: [],
        max_frequency: '',
        variable_hours: false,
        available_for_training: false,
        commitments: '',
        motivations: '',
        comments: '',
        consent_accepted: false,
        consent_version: declaration.version,
        'cf-turnstile-response': '',
        ...draft?.data,
    });

    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;
    const age = ageOn(data.birth_date);
    const isMinor = age !== null && age < ADULT_AGE;

    // Keep a draft of the non-sensitive fields so a reload on a weak connection loses little.
    useEffect(() => {
        try {
            const draftData = Object.fromEntries(
                DRAFT_FIELDS.map((field) => [field, data[field]]),
            );
            window.sessionStorage.setItem(
                DRAFT_KEY,
                JSON.stringify({ step, data: draftData }),
            );
        } catch {
            // Storage unavailable (private mode): nothing to keep.
        }
    }, [data, step]);

    const onTurnstileToken = useCallback(
        (token: string) => setData('cf-turnstile-response', token),
        [setData],
    );

    const goTo = (target: number) => {
        setStepError(null);
        setStep(target);
        window.scrollTo({ top: 0 });
    };

    /** Light checks so nobody gets far with an empty required section; the server decides. */
    const checkStep = (): string | null => {
        switch (step) {
            case 0:
                if (data.full_name.trim().length < 3)
                    return 'Indique o seu nome completo.';
                if (age === null) return 'Indique a data de nascimento.';
                if (age < MINIMUM_AGE)
                    return 'É preciso ter pelo menos 14 anos para se registar.';
                if (
                    isMinor &&
                    (!data.guardian_name.trim() || !data.guardian_phone.trim())
                ) {
                    return 'Menores de 18 anos têm de indicar o nome e o telefone do encarregado.';
                }
                return null;
            case 1:
                if (!data.phone.trim())
                    return 'Indique o seu número de telefone.';
                if (data.password.length < 8)
                    return 'A palavra-passe tem de ter pelo menos 8 caracteres.';
                if (data.password !== data.password_confirmation)
                    return 'As palavras-passe não coincidem.';
                return null;
            case 3:
                if (data.skills.length === 0)
                    return 'Escolha pelo menos uma área de serviço.';
                if (!data.level) return 'Indique o seu nível de experiência.';
                return null;
            case 4:
                if (data.availability.length === 0)
                    return 'Indique pelo menos um dia e período.';
                if (!data.max_frequency)
                    return 'Indique com que frequência pode servir.';
                return null;
            default:
                return null;
        }
    };

    const next = () => {
        const problem = checkStep();

        if (problem) {
            setStepError(problem);

            return;
        }

        goTo(step + 1);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (step < STEPS.length - 1) {
            next();

            return;
        }

        // Honeypot fields have server-generated names, so they are added at submit time.
        form.transform((formData) => ({
            ...formData,
            ...(honeypot.enabled
                ? {
                      [honeypot.nameFieldName]: honeypotValue,
                      [honeypot.validFromFieldName]:
                          honeypot.encryptedValidFrom,
                  }
                : {}),
        }));
        form.post(store().url, {
            onSuccess: () => {
                try {
                    window.sessionStorage.removeItem(DRAFT_KEY);
                } catch {
                    // ignore
                }
            },
            onError: (serverErrors) => {
                const first = Object.keys(serverErrors)[0];

                if (first) {
                    goTo(stepOfError(first));
                }
            },
        });
    };

    const current = STEPS[step];

    return (
        <>
            <Head title="Cadastro de voluntário" />

            <h1 className="text-2xl font-semibold">Cadastro de voluntário</h1>
            <p className="mt-1 text-sm text-muted-foreground">
                Equipa de Rádio e Televisão Alfa &amp; Ómega. Leva cerca de 10
                minutos.
            </p>

            <nav aria-label="Progresso do cadastro" className="mt-6">
                <p className="text-sm font-medium" aria-live="polite">
                    Passo {step + 1} de {STEPS.length}: {current.title}
                </p>
                <ol className="mt-2 flex gap-1" aria-hidden="true">
                    {STEPS.map((item, index) => (
                        <li
                            key={item.title}
                            className={`h-1.5 flex-1 rounded-full ${index <= step ? 'bg-primary' : 'bg-muted'}`}
                        />
                    ))}
                </ol>
            </nav>

            <form onSubmit={submit} className="mt-6 space-y-6" noValidate>
                {honeypot.enabled && (
                    <div
                        aria-hidden="true"
                        className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
                    >
                        <label>
                            Não preencher
                            <input
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                value={honeypotValue}
                                onChange={(event) =>
                                    setHoneypotValue(event.target.value)
                                }
                            />
                        </label>
                    </div>
                )}

                <h2 className="text-lg font-semibold">
                    {step < STEPS.length - 1 ? `${toRoman(step + 1)}. ` : ''}
                    {current.title}
                </h2>

                {step === 0 && (
                    <>
                        <TextField
                            label="Nome completo"
                            value={data.full_name}
                            onChange={(event) =>
                                setData('full_name', event.target.value)
                            }
                            autoComplete="name"
                            required
                            error={fieldErrors.full_name}
                        />
                        <TextField
                            label="Data de nascimento"
                            type="date"
                            value={data.birth_date}
                            onChange={(event) =>
                                setData('birth_date', event.target.value)
                            }
                            autoComplete="bday"
                            required
                            hint="É preciso ter pelo menos 14 anos."
                            error={fieldErrors.birth_date}
                        />
                        <TextField
                            label="Bairro onde vive"
                            optional
                            value={data.neighbourhood}
                            onChange={(event) =>
                                setData('neighbourhood', event.target.value)
                            }
                            error={fieldErrors.neighbourhood}
                        />
                        {isMinor && (
                            <div className="space-y-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                                <p className="text-sm">
                                    Como tem menos de 18 anos, indique o seu
                                    encarregado de educação. Um coordenador vai
                                    confirmar o consentimento dele.
                                </p>
                                <TextField
                                    label="Nome do encarregado"
                                    value={data.guardian_name}
                                    onChange={(event) =>
                                        setData(
                                            'guardian_name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    error={fieldErrors.guardian_name}
                                />
                                <PhoneField
                                    label="Telefone do encarregado"
                                    value={data.guardian_phone}
                                    onChange={(event) =>
                                        setData(
                                            'guardian_phone',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    error={fieldErrors.guardian_phone}
                                />
                            </div>
                        )}
                    </>
                )}

                {step === 1 && (
                    <>
                        <PhoneField
                            label="Número de telefone"
                            autoComplete="tel-national"
                            placeholder="84 123 4567"
                            value={data.phone}
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                            required
                            hint="Vai usar este número para entrar no Cue App."
                            error={fieldErrors.phone}
                        />
                        <TextField
                            label="E-mail"
                            optional
                            type="email"
                            autoComplete="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            hint="Com e-mail pode recuperar a palavra-passe sozinho."
                            error={fieldErrors.email}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="password">Palavra-passe</Label>
                            <PasswordInput
                                id="password"
                                value={data.password}
                                onChange={(event) =>
                                    setData('password', event.target.value)
                                }
                                autoComplete="new-password"
                                className="min-h-11"
                                aria-describedby="password-hint"
                            />
                            <p
                                id="password-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Pelo menos 8 caracteres.
                            </p>
                            <InputError message={fieldErrors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Repetir a palavra-passe
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(event) =>
                                    setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                className="min-h-11"
                            />
                        </div>
                    </>
                )}

                {step === 2 && (
                    <>
                        <TextField
                            label="Escolaridade"
                            optional
                            placeholder="Ex.: 12.ª classe, licenciatura"
                            value={data.education_level}
                            onChange={(event) =>
                                setData('education_level', event.target.value)
                            }
                            error={fieldErrors.education_level}
                        />
                        <TextField
                            label="Formação ou profissão"
                            optional
                            placeholder="Ex.: informática, electricidade, design"
                            value={data.training}
                            onChange={(event) =>
                                setData('training', event.target.value)
                            }
                            error={fieldErrors.training}
                        />
                    </>
                )}

                {step === 3 && (
                    <>
                        <RolePicker
                            roles={teamRoles}
                            value={data.skills}
                            onChange={(skills) => setData('skills', skills)}
                            preferredRoleId={data.preferred_role_id}
                            onPreferredChange={(roleId) =>
                                setData('preferred_role_id', roleId)
                            }
                            error={fieldErrors.skills}
                        />
                        <RadioGroupField
                            legend="Qual é o seu nível de experiência?"
                            name="level"
                            options={levels}
                            value={data.level}
                            onChange={(level) => setData('level', level)}
                            hint="Um coordenador pode ajustar o nível em cada área."
                            error={fieldErrors.level}
                        />
                        <CheckboxField
                            label="Posso apoiar e ensinar outros membros"
                            checked={data.can_support_others}
                            onChange={(checked) =>
                                setData('can_support_others', checked)
                            }
                        />
                    </>
                )}

                {step === 4 && (
                    <>
                        <AvailabilityGrid
                            weekdays={weekdays}
                            periods={periods}
                            value={data.availability}
                            onChange={(slots) => setData('availability', slots)}
                            error={fieldErrors.availability}
                        />
                        <RadioGroupField
                            legend="Com que frequência pode servir?"
                            name="max_frequency"
                            options={frequencies}
                            value={data.max_frequency}
                            onChange={(frequency) =>
                                setData('max_frequency', frequency)
                            }
                            error={fieldErrors.max_frequency}
                        />
                        <CheckboxField
                            label="Tenho horários variáveis"
                            hint="Pode ser escalado em qualquer dia, com menos prioridade."
                            checked={data.variable_hours}
                            onChange={(checked) =>
                                setData('variable_hours', checked)
                            }
                        />
                        <CheckboxField
                            label="Estou disponível para formações e reuniões"
                            checked={data.available_for_training}
                            onChange={(checked) =>
                                setData('available_for_training', checked)
                            }
                        />
                    </>
                )}

                {step === 5 && (
                    <TextAreaField
                        label="Tem outros compromissos na igreja ou no trabalho?"
                        optional
                        hint="Ajuda a evitar escalas em dias em que já está ocupado."
                        value={data.commitments}
                        onChange={(event) =>
                            setData('commitments', event.target.value)
                        }
                        error={fieldErrors.commitments}
                    />
                )}

                {step === 6 && (
                    <>
                        <TextAreaField
                            label="Porque quer servir na equipa de mídia?"
                            optional
                            value={data.motivations}
                            onChange={(event) =>
                                setData('motivations', event.target.value)
                            }
                            error={fieldErrors.motivations}
                        />
                        <TextAreaField
                            label="Comentários"
                            optional
                            value={data.comments}
                            onChange={(event) =>
                                setData('comments', event.target.value)
                            }
                            error={fieldErrors.comments}
                        />
                    </>
                )}

                {step === 7 && (
                    <>
                        <div className="max-h-80 overflow-y-auto rounded-lg border bg-muted/30 p-4 text-sm leading-6 whitespace-pre-line">
                            {declaration.body}
                        </div>
                        <CheckboxField
                            label="Li e aceito a declaração"
                            checked={data.consent_accepted}
                            onChange={(checked) =>
                                setData('consent_accepted', checked)
                            }
                            error={
                                fieldErrors.consent_accepted ??
                                fieldErrors.consent_version
                            }
                        />
                        <Turnstile
                            siteKey={turnstileSiteKey}
                            onToken={onTurnstileToken}
                        />
                        <InputError
                            message={
                                fieldErrors['cf-turnstile-response'] ??
                                fieldErrors.form
                            }
                        />
                    </>
                )}

                {stepError && (
                    <p
                        role="alert"
                        className="text-sm font-medium text-red-600 dark:text-red-400"
                    >
                        {stepError}
                    </p>
                )}

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    {step > 0 ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => goTo(step - 1)}
                        >
                            Voltar
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={
                            processing ||
                            (step === STEPS.length - 1 &&
                                !data.consent_accepted)
                        }
                    >
                        {processing && <Spinner />}
                        {step < STEPS.length - 1
                            ? 'Continuar'
                            : 'Enviar cadastro'}
                    </Button>
                </div>
            </form>
        </>
    );
}

function toRoman(value: number): string {
    return (
        ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'][value - 1] ?? String(value)
    );
}
