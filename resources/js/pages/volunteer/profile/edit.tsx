import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DataRemovalCard } from '@/components/app/data-removal-card';
import { PauseCard } from '@/components/app/pause-card';
import Heading from '@/components/heading';
import { localPhone } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AvailabilityGrid } from '@/components/volunteer-form/availability-grid';
import {
    CheckboxField,
    PhoneField,
    RadioGroupField,
    TextAreaField,
    TextField,
} from '@/components/volunteer-form/fields';
import { RolePicker } from '@/components/volunteer-form/role-picker';
import type {
    Option,
    TeamRoleOption,
    VolunteerFormFields,
} from '@/components/volunteer-form/types';
import { pause, resume } from '@/routes/profile';
import { edit, update } from '@/routes/volunteer/profile';
import type { VolunteerFull } from '@/types';

type Props = {
    volunteer: VolunteerFull;
    canEditIdentity: boolean;
    teamRoles: TeamRoleOption[];
    frequencies: Option[];
    periods: Option[];
    weekdays: Option<number>[];
};

type ProfileForm = VolunteerFormFields & {
    full_name: string;
    birth_date: string;
};

/**
 * The volunteer's own data (FR-005). Levels are set by coordinators; name and birth
 * date can only change while the registration is under review.
 */
export default function VolunteerProfileEdit({
    volunteer,
    canEditIdentity,
    teamRoles,
    frequencies,
    periods,
    weekdays,
}: Props) {
    const form = useForm<ProfileForm>({
        full_name: volunteer.fullName,
        birth_date: volunteer.birthDate,
        neighbourhood: volunteer.neighbourhood ?? '',
        guardian_name: volunteer.guardianName ?? '',
        guardian_phone: volunteer.guardianPhone
            ? localPhone(volunteer.guardianPhone)
            : '',
        education_level: volunteer.educationLevel ?? '',
        training: volunteer.training ?? '',
        skills: volunteer.skills.map((skill) => ({
            team_role_id: skill.teamRoleId,
            has_experience: skill.hasExperience,
        })),
        preferred_role_id:
            volunteer.skills.find((skill) => skill.isPreferred)?.teamRoleId ??
            null,
        can_support_others: volunteer.canSupportOthers,
        availability: volunteer.availability.map(({ weekday, period }) => ({
            weekday,
            period,
        })),
        max_frequency: volunteer.maxFrequency,
        variable_hours: volunteer.variableHours,
        available_for_training: volunteer.availableForTraining,
        commitments: volunteer.commitments ?? '',
        motivations: volunteer.motivations ?? '',
        comments: volunteer.comments ?? '',
    });

    const { data, setData, processing } = form;
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Os meus dados" />

            <form onSubmit={submit} className="space-y-10">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Identificação"
                        description="Só os coordenadores e você vêem estes dados."
                    />
                    <TextField
                        label="Nome completo"
                        value={data.full_name}
                        onChange={(event) =>
                            setData('full_name', event.target.value)
                        }
                        readOnly={!canEditIdentity}
                        hint={
                            canEditIdentity
                                ? undefined
                                : 'Para alterar, fale com um coordenador.'
                        }
                        error={errors.full_name}
                    />
                    <TextField
                        label="Data de nascimento"
                        type="date"
                        value={data.birth_date}
                        onChange={(event) =>
                            setData('birth_date', event.target.value)
                        }
                        readOnly={!canEditIdentity}
                        error={errors.birth_date}
                    />
                    <TextField
                        label="Bairro onde vive"
                        optional
                        value={data.neighbourhood}
                        onChange={(event) =>
                            setData('neighbourhood', event.target.value)
                        }
                        error={errors.neighbourhood}
                    />
                    {volunteer.isMinor && (
                        <>
                            <TextField
                                label="Nome do encarregado"
                                value={data.guardian_name}
                                onChange={(event) =>
                                    setData('guardian_name', event.target.value)
                                }
                                error={errors.guardian_name}
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
                                error={errors.guardian_phone}
                            />
                        </>
                    )}
                </section>

                <section className="space-y-4">
                    <Heading variant="small" title="Escolaridade e formação" />
                    <TextField
                        label="Escolaridade"
                        optional
                        value={data.education_level}
                        onChange={(event) =>
                            setData('education_level', event.target.value)
                        }
                        error={errors.education_level}
                    />
                    <TextField
                        label="Formação ou profissão"
                        optional
                        value={data.training}
                        onChange={(event) =>
                            setData('training', event.target.value)
                        }
                        error={errors.training}
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Áreas de serviço"
                        description="Os níveis em cada área são definidos pelos coordenadores."
                    />
                    <RolePicker
                        roles={teamRoles}
                        value={data.skills}
                        onChange={(skills) => setData('skills', skills)}
                        preferredRoleId={data.preferred_role_id}
                        onPreferredChange={(roleId) =>
                            setData('preferred_role_id', roleId)
                        }
                        error={errors.skills}
                    />
                    <CheckboxField
                        label="Posso apoiar e ensinar outros membros"
                        checked={data.can_support_others}
                        onChange={(checked) =>
                            setData('can_support_others', checked)
                        }
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Disponibilidade"
                        description="As alterações aplicam-se às escalas ainda não publicadas."
                    />
                    <AvailabilityGrid
                        weekdays={weekdays}
                        periods={periods}
                        value={data.availability}
                        onChange={(slots) => setData('availability', slots)}
                        error={errors.availability}
                    />
                    <RadioGroupField
                        legend="Com que frequência pode servir?"
                        name="max_frequency"
                        options={frequencies}
                        value={data.max_frequency}
                        onChange={(frequency) =>
                            setData('max_frequency', frequency)
                        }
                        error={errors.max_frequency}
                    />
                    <CheckboxField
                        label="Tenho horários variáveis"
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
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Compromissos e motivações"
                    />
                    <TextAreaField
                        label="Outros compromissos"
                        optional
                        value={data.commitments}
                        onChange={(event) =>
                            setData('commitments', event.target.value)
                        }
                        error={errors.commitments}
                    />
                    <TextAreaField
                        label="Motivações"
                        optional
                        value={data.motivations}
                        onChange={(event) =>
                            setData('motivations', event.target.value)
                        }
                        error={errors.motivations}
                    />
                    <TextAreaField
                        label="Comentários"
                        optional
                        value={data.comments}
                        onChange={(event) =>
                            setData('comments', event.target.value)
                        }
                        error={errors.comments}
                    />
                </section>

                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    Guardar
                </Button>
            </form>

            {(volunteer.status === 'active' ||
                volunteer.status === 'inactive') && (
                <div className="mt-10">
                    <PauseCard
                        paused={volunteer.status === 'inactive'}
                        pausedUntil={volunteer.inactiveUntil}
                        pauseUrl={pause().url}
                        resumeUrl={resume().url}
                    />
                </div>
            )}

            <div className="mt-10">
                <DataRemovalCard scheduledFor={volunteer.removalScheduledFor} />
            </div>
        </>
    );
}

VolunteerProfileEdit.layout = {
    breadcrumbs: [{ title: 'Os meus dados', href: edit() }],
};
