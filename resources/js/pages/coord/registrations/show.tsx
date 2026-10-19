import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { VolunteerProfileDetails } from '@/components/app/volunteer-profile-details';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    RadioGroupField,
    TextAreaField,
} from '@/components/volunteer-form/fields';
import type { Option } from '@/components/volunteer-form/types';
import { formatDate } from '@/lib/format';
import {
    approve,
    correction,
    index,
    reject,
    reverse,
} from '@/routes/coord/registrations';
import type { VolunteerFull } from '@/types';

type Props = {
    volunteer: VolunteerFull;
    levels: Option[];
    guardianConsentMethods: Option[];
    canReverse: boolean;
};

export default function RegistrationShow({
    volunteer,
    levels,
    guardianConsentMethods,
    canReverse,
}: Props) {
    const underReview =
        volunteer.status === 'pending' ||
        volunteer.status === 'needs_correction';
    const needsGuardianConsent =
        volunteer.isMinor && volunteer.guardianConsent === null;

    const approval = useForm({
        levels: Object.fromEntries(
            volunteer.skills.map((skill) => [skill.teamRoleId, skill.level]),
        ) as Record<number, string>,
        guardian_consent_method: '',
    });

    const decision = useForm({ notes: '' });
    const errors = { ...approval.errors, ...decision.errors } as Record<
        string,
        string | undefined
    >;

    const submitApproval = (event: FormEvent) => {
        event.preventDefault();
        approval.post(approve(volunteer.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={volunteer.fullName} />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">
                        {volunteer.fullName}
                    </h1>
                    <div className="flex flex-wrap gap-2">
                        <Badge>{volunteer.statusLabel}</Badge>
                        {volunteer.isMinor && (
                            <Badge variant="secondary">
                                Menor ({volunteer.age} anos)
                            </Badge>
                        )}
                        {volunteer.previouslyRejectedAt && (
                            <Badge variant="outline">
                                Cadastro anterior rejeitado em{' '}
                                {formatDate(volunteer.previouslyRejectedAt)}
                            </Badge>
                        )}
                    </div>
                    {volunteer.reviewNotes && (
                        <p className="text-sm text-muted-foreground">
                            Notas: {volunteer.reviewNotes}
                        </p>
                    )}
                    <InputError message={errors.volunteer} />
                </header>

                <VolunteerProfileDetails volunteer={volunteer} />

                {underReview && (
                    <form
                        onSubmit={submitApproval}
                        className="space-y-4 rounded-lg border p-4"
                    >
                        <Heading
                            variant="small"
                            title="Aprovar"
                            description="Confirme o nível em cada área. O voluntário passa a poder ser escalado."
                        />
                        <ul className="grid gap-3">
                            {volunteer.skills.map((skill) => (
                                <li
                                    key={skill.teamRoleId}
                                    className="grid gap-2 sm:grid-cols-[1fr_12rem] sm:items-center"
                                >
                                    <Label
                                        htmlFor={`level-${skill.teamRoleId}`}
                                    >
                                        {skill.name}
                                        {skill.hasExperience && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · com experiência
                                            </span>
                                        )}
                                        {skill.isPreferred && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · maior interesse
                                            </span>
                                        )}
                                    </Label>
                                    <Select
                                        value={
                                            approval.data.levels[
                                                skill.teamRoleId
                                            ]
                                        }
                                        onValueChange={(level) =>
                                            approval.setData('levels', {
                                                ...approval.data.levels,
                                                [skill.teamRoleId]: level,
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`level-${skill.teamRoleId}`}
                                            className="min-h-11"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {levels.map((level) => (
                                                <SelectItem
                                                    key={level.value}
                                                    value={level.value}
                                                >
                                                    {level.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </li>
                            ))}
                        </ul>

                        {needsGuardianConsent && (
                            <RadioGroupField
                                legend="Consentimento do encarregado"
                                hint="Confirme com o encarregado antes de aprovar. Fica registado o seu nome e a data."
                                name="guardian_consent_method"
                                options={guardianConsentMethods}
                                value={approval.data.guardian_consent_method}
                                onChange={(method) =>
                                    approval.setData(
                                        'guardian_consent_method',
                                        method,
                                    )
                                }
                                error={errors.guardian_consent_method}
                            />
                        )}

                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={
                                approval.processing ||
                                (needsGuardianConsent &&
                                    !approval.data.guardian_consent_method)
                            }
                        >
                            {approval.processing && <Spinner />}
                            Aprovar
                        </Button>
                    </form>
                )}

                {underReview && (
                    <section className="space-y-4 rounded-lg border p-4">
                        <Heading
                            variant="small"
                            title="Pedir correcção ou rejeitar"
                            description="A mensagem é mostrada ao voluntário."
                        />
                        <TextAreaField
                            label="Mensagem para o voluntário"
                            optional
                            hint="Obrigatória para pedir correcção."
                            value={decision.data.notes}
                            onChange={(event) =>
                                decision.setData('notes', event.target.value)
                            }
                            error={errors.notes}
                        />
                        <div className="flex flex-col gap-3 sm:flex-row">
                            {volunteer.status === 'pending' && (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    disabled={decision.processing}
                                    onClick={() =>
                                        decision.post(
                                            correction(volunteer.id).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Pedir correcção
                                </Button>
                            )}
                            <ConfirmDialog
                                title="Rejeitar este cadastro?"
                                description={`${volunteer.fullName} será notificado. Pode reverter durante 30 dias; depois os dados pessoais são apagados.`}
                                confirmLabel="Rejeitar"
                                destructive
                                onConfirm={() =>
                                    decision.post(reject(volunteer.id).url)
                                }
                            >
                                <Button
                                    variant="destructive"
                                    className="min-h-11"
                                    disabled={decision.processing}
                                >
                                    Rejeitar
                                </Button>
                            </ConfirmDialog>
                        </div>
                    </section>
                )}

                {volunteer.status === 'rejected' && canReverse && (
                    <section className="space-y-3 rounded-lg border p-4">
                        <Heading
                            variant="small"
                            title="Reverter a rejeição"
                            description="O cadastro volta a pendente. Disponível durante 30 dias após a rejeição."
                        />
                        <Button
                            variant="outline"
                            className="min-h-11"
                            onClick={() =>
                                router.post(reverse(volunteer.id).url)
                            }
                        >
                            Reverter rejeição
                        </Button>
                    </section>
                )}
            </div>
        </>
    );
}

RegistrationShow.layout = {
    breadcrumbs: [{ title: 'Cadastros', href: index() }],
};
