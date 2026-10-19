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
import { EducationField } from '@/components/volunteer-form/education-field';
import { ExperiencePicker } from '@/components/volunteer-form/experience-picker';
import { RolePicker } from '@/components/volunteer-form/role-picker';
import { SocialMediaList } from '@/components/volunteer-form/social-media-list';
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
    trainingAvailabilities: Option[];
    socialNetworks: string[];
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
    trainingAvailabilities,
    socialNetworks,
}: Props) {
    const form = useForm<ProfileForm>({
        full_name: volunteer.fullName,
        birth_date: volunteer.birthDate,
        nickname: volunteer.nickname ?? '',
        neighbourhood: volunteer.neighbourhood ?? '',
        phone_has_whatsapp: volunteer.phoneHasWhatsapp,
        whatsapp_phone: volunteer.whatsappPhone
            ? localPhone(volunteer.whatsappPhone)
            : '',
        social_media: volunteer.socialMedia,
        guardian_name: volunteer.guardianName ?? '',
        guardian_phone: volunteer.guardianPhone
            ? localPhone(volunteer.guardianPhone)
            : '',
        education_level: volunteer.educationLevel ?? '',
        training: volunteer.training ?? '',
        skills: volunteer.skills.map((skill) => ({
            team_role_id: skill.teamRoleId,
        })),
        preferred_role_id:
            volunteer.skills.find((skill) => skill.isPreferred)?.teamRoleId ??
            null,
        other_area: volunteer.otherArea ?? '',
        experience_role_ids: volunteer.experienceRoleIds,
        other_experience: volunteer.otherExperience ?? '',
        no_experience:
            volunteer.experienceRoleIds.length === 0 &&
            !volunteer.otherExperience,
        experience_description: volunteer.experienceDescription ?? '',
        can_support_others: volunteer.canSupportOthers,
        availability: volunteer.availability.map(({ weekday, period }) => ({
            weekday,
            period,
        })),
        other_days_by_arrangement: volunteer.otherDaysByArrangement,
        max_frequency: volunteer.maxFrequency,
        frequency_note: volunteer.frequencyNote ?? '',
        variable_hours: volunteer.variableHours,
        training_availability: volunteer.trainingAvailability,
        commitments: volunteer.commitments ?? '',
        motivations: volunteer.motivations ?? '',
        expectations: volunteer.expectations ?? '',
        goals: volunteer.goals ?? '',
        comments: volunteer.comments ?? '',
        suggestions: volunteer.suggestions ?? '',
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
                        description="Só os coordenadores e o próprio voluntário vêem estes dados."
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
                        label="Alcunha ou nome pelo qual é conhecido"
                        optional
                        maxLength={80}
                        value={data.nickname}
                        onChange={(event) =>
                            setData('nickname', event.target.value)
                        }
                        error={errors.nickname}
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
                    <CheckboxField
                        label="Uso o meu número de telefone no WhatsApp"
                        checked={data.phone_has_whatsapp}
                        onChange={(checked) =>
                            setData('phone_has_whatsapp', checked)
                        }
                    />
                    {!data.phone_has_whatsapp && (
                        <PhoneField
                            label="Número de WhatsApp"
                            optional
                            value={data.whatsapp_phone}
                            onChange={(event) =>
                                setData('whatsapp_phone', event.target.value)
                            }
                            hint="Se tem WhatsApp noutro número."
                            error={errors.whatsapp_phone}
                        />
                    )}
                    <SocialMediaList
                        networks={socialNetworks}
                        value={data.social_media}
                        onChange={(profiles) =>
                            setData('social_media', profiles)
                        }
                        errors={errors}
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
                    <EducationField
                        value={data.education_level}
                        onChange={(level) => setData('education_level', level)}
                        error={errors.education_level}
                    />
                    <TextField
                        label="Cursos, certificados ou formações"
                        optional
                        maxLength={255}
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
                    <TextField
                        label="Outra área"
                        optional
                        maxLength={120}
                        value={data.other_area}
                        onChange={(event) =>
                            setData('other_area', event.target.value)
                        }
                        error={errors.other_area}
                    />
                    <ExperiencePicker
                        roles={teamRoles}
                        value={data.experience_role_ids}
                        onChange={(roleIds) =>
                            setData('experience_role_ids', roleIds)
                        }
                        other={data.other_experience}
                        onOtherChange={(other) =>
                            setData('other_experience', other)
                        }
                        noExperience={data.no_experience}
                        onNoExperienceChange={(noExperience) =>
                            setData('no_experience', noExperience)
                        }
                        error={
                            errors.experience_role_ids ??
                            errors.other_experience
                        }
                    />
                    <TextAreaField
                        label="Descreva brevemente a sua experiência"
                        optional
                        hint="Há quanto tempo, onde, e que equipamentos ou programas sabe usar."
                        value={data.experience_description}
                        onChange={(event) =>
                            setData(
                                'experience_description',
                                event.target.value,
                            )
                        }
                        error={errors.experience_description}
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
                    <CheckboxField
                        label="Posso servir noutros dias, mediante combinação prévia"
                        checked={data.other_days_by_arrangement}
                        onChange={(checked) =>
                            setData('other_days_by_arrangement', checked)
                        }
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
                    <TextField
                        label="Outra situação ou detalhe sobre a frequência"
                        optional
                        maxLength={255}
                        value={data.frequency_note}
                        onChange={(event) =>
                            setData('frequency_note', event.target.value)
                        }
                        error={errors.frequency_note}
                    />
                    <CheckboxField
                        label="Tenho horários variáveis, conforme a minha agenda"
                        checked={data.variable_hours}
                        onChange={(checked) =>
                            setData('variable_hours', checked)
                        }
                    />
                    <RadioGroupField
                        legend="Pode participar em reuniões, formações, ensaios técnicos e outras actividades extraordinárias?"
                        name="training_availability"
                        options={trainingAvailabilities}
                        value={data.training_availability}
                        onChange={(answer) =>
                            setData('training_availability', answer)
                        }
                        error={errors.training_availability}
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Compromissos, motivações e sugestões"
                    />
                    <TextAreaField
                        label="Compromissos que limitam a sua disponibilidade"
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
                        label="O que espera aprender ou alcançar"
                        optional
                        value={data.expectations}
                        onChange={(event) =>
                            setData('expectations', event.target.value)
                        }
                        error={errors.expectations}
                    />
                    <TextAreaField
                        label="Sonhos, projectos ou objectivos"
                        optional
                        value={data.goals}
                        onChange={(event) =>
                            setData('goals', event.target.value)
                        }
                        error={errors.goals}
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
                    <TextAreaField
                        label="Sugestões para a equipa"
                        optional
                        value={data.suggestions}
                        onChange={(event) =>
                            setData('suggestions', event.target.value)
                        }
                        error={errors.suggestions}
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
