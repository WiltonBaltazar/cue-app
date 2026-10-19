import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AvailabilityGrid } from '@/components/volunteer-form/availability-grid';
import {
    CheckboxField,
    RadioGroupField,
    TextField,
} from '@/components/volunteer-form/fields';
import type {
    AvailabilitySlot,
    Option,
    TeamRoleOption,
} from '@/components/volunteer-form/types';
import { MINIMUM_AGE, ageOn } from '@/components/volunteer-form/types';
import { create, store } from '@/routes/coord/serving-profile';

type Skill = { team_role_id: number; level: string };

type Form = {
    full_name: string;
    birth_date: string;
    skills: Skill[];
    availability: AvailabilitySlot[];
    max_frequency: string;
    consent_accepted: boolean;
    consent_version: number;
};

/**
 * "Também sirvo na equipa" (coordinator): the few answers scheduling needs, so the
 * coordinator can be placed on schedules like any volunteer. Everything else can be
 * filled in later in "Os meus dados".
 */
export default function ServingProfileCreate({
    name,
    declaration,
    teamRoles,
    levels,
    frequencies,
    periods,
    weekdays,
}: {
    name: string;
    declaration: { version: number; body: string };
    teamRoles: TeamRoleOption[];
    levels: Option[];
    frequencies: Option[];
    periods: Option[];
    weekdays: Option<number>[];
}) {
    const form = useForm<Form>({
        full_name: name,
        birth_date: '',
        skills: [],
        availability: [],
        max_frequency: '',
        consent_accepted: false,
        consent_version: declaration.version,
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;
    const age = ageOn(data.birth_date);

    const levelOf = (roleId: number) =>
        data.skills.find((skill) => skill.team_role_id === roleId)?.level ?? '';

    const setLevel = (roleId: number, level: string) =>
        setData(
            'skills',
            level === ''
                ? data.skills.filter((skill) => skill.team_role_id !== roleId)
                : [
                      ...data.skills.filter(
                          (skill) => skill.team_role_id !== roleId,
                      ),
                      { team_role_id: roleId, level },
                  ],
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Também sirvo na equipa" />

            <form
                onSubmit={submit}
                className="mx-auto w-full max-w-2xl space-y-8 p-4 md:p-6"
                noValidate
            >
                <Heading
                    title="Também sirvo na equipa"
                    description="Para entrar nas escalas como os outros voluntários: as funções em que serve, quando pode e com que frequência. Os restantes dados ficam para depois, em Os meus dados."
                />

                <section className="space-y-4">
                    <Heading variant="small" title="Identificação" />
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
                        required
                        hint={
                            age !== null && age < MINIMUM_AGE
                                ? `É preciso ter pelo menos ${MINIMUM_AGE} anos.`
                                : 'Só os coordenadores a vêem.'
                        }
                        error={fieldErrors.birth_date}
                    />
                </section>

                <fieldset className="space-y-3">
                    <legend className="mb-1 font-medium">
                        Em que funções serve, e com que nível?
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        Deixe "Não sirvo" nas funções que não faz. Pode mudar o
                        nível mais tarde, como o de qualquer voluntário.
                    </p>
                    <ul className="divide-y rounded-xl border">
                        {teamRoles.map((role) => {
                            const id = `level-${role.id}`;

                            return (
                                <li
                                    key={role.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                                >
                                    <label htmlFor={id} className="font-medium">
                                        {role.name}
                                    </label>
                                    <select
                                        id={id}
                                        value={levelOf(role.id)}
                                        onChange={(event) =>
                                            setLevel(
                                                role.id,
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-11 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Não sirvo</option>
                                        {levels.map((level) => (
                                            <option
                                                key={level.value}
                                                value={level.value}
                                            >
                                                {level.label}
                                            </option>
                                        ))}
                                    </select>
                                </li>
                            );
                        })}
                    </ul>
                    <InputError message={fieldErrors.skills} />
                </fieldset>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Quando pode servir"
                        description="Os dias e períodos habituais. Datas em que não pode, marca-as depois em Ausências."
                    />
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
                        onChange={(value) => setData('max_frequency', value)}
                        error={fieldErrors.max_frequency}
                    />
                </section>

                <section className="space-y-3">
                    <Heading variant="small" title="Declaração" />
                    <div className="max-h-64 overflow-y-auto rounded-lg border bg-muted/30 p-4 text-sm leading-6 whitespace-pre-line">
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
                </section>

                <InputError message={fieldErrors.form} />

                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={processing || !data.consent_accepted}
                >
                    {processing && <Spinner />}
                    Entrar nas escalas
                </Button>
            </form>
        </>
    );
}

ServingProfileCreate.layout = {
    breadcrumbs: [{ title: 'Também sirvo na equipa', href: create() }],
};
