import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { PauseCard } from '@/components/app/pause-card';
import { VolunteerProfileDetails } from '@/components/app/volunteer-profile-details';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { Option } from '@/components/volunteer-form/types';
import { useClipboard } from '@/hooks/use-clipboard';
import { store as issueResetLink } from '@/routes/coord/reset-links';
import {
    history,
    index,
    update,
    pause as pauseVolunteer,
    resume as resumeVolunteer,
} from '@/routes/coord/volunteers';
import type { VolunteerFull } from '@/types';

type Props = {
    volunteer: VolunteerFull;
    levels: Option[];
    resetLink: string | null;
    resetLinkHours: number;
};

export default function VolunteerEdit({
    volunteer,
    levels,
    resetLink,
    resetLinkHours,
}: Props) {
    const [copiedText, copy] = useClipboard();
    const form = useForm({
        levels: Object.fromEntries(
            volunteer.skills.map((skill) => [skill.teamRoleId, skill.level]),
        ) as Record<number, string>,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update(volunteer.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={volunteer.fullName} />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">
                        {volunteer.fullName}
                    </h1>
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge>{volunteer.statusLabel}</Badge>
                        <Link
                            href={history(volunteer.id)}
                            className="text-sm underline-offset-4 hover:underline"
                        >
                            Ver histórico de participação
                        </Link>
                    </div>
                </header>

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-lg border p-4"
                >
                    <Heading
                        variant="small"
                        title="Nível por área"
                        description="Usado na geração automática da escala."
                    />
                    <ul className="grid gap-3">
                        {volunteer.skills.map((skill) => (
                            <li
                                key={skill.teamRoleId}
                                className="grid gap-2 sm:grid-cols-[1fr_12rem] sm:items-center"
                            >
                                <Label htmlFor={`level-${skill.teamRoleId}`}>
                                    {skill.name}
                                </Label>
                                <Select
                                    value={form.data.levels[skill.teamRoleId]}
                                    onValueChange={(level) =>
                                        form.setData('levels', {
                                            ...form.data.levels,
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
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Guardar níveis
                    </Button>
                </form>

                <section className="space-y-3 rounded-lg border p-4">
                    <Heading
                        variant="small"
                        title="Link para nova palavra-passe"
                        description={`Para quem não tem e-mail. O link funciona uma vez e expira em ${resetLinkHours} horas. Entregue-o pessoalmente.`}
                    />
                    {resetLink ? (
                        <div className="space-y-2">
                            <Label htmlFor="reset-link">
                                Link (só é mostrado agora)
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="reset-link"
                                    value={resetLink}
                                    readOnly
                                    className="min-h-11 font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => void copy(resetLink)}
                                    aria-label="Copiar link"
                                >
                                    {copiedText === resetLink ? (
                                        <Check />
                                    ) : (
                                        <Copy />
                                    )}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <ConfirmDialog
                            title="Gerar um link para nova palavra-passe?"
                            description={`Qualquer link anterior de ${volunteer.fullName} deixa de funcionar.`}
                            confirmLabel="Gerar link"
                            onConfirm={() =>
                                router.post(
                                    issueResetLink(volunteer.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Button variant="outline" className="min-h-11">
                                Gerar link
                            </Button>
                        </ConfirmDialog>
                    )}
                </section>

                {(volunteer.status === 'active' ||
                    volunteer.status === 'inactive') && (
                    <PauseCard
                        paused={volunteer.status === 'inactive'}
                        pausedUntil={volunteer.inactiveUntil}
                        pauseUrl={pauseVolunteer(volunteer.id).url}
                        resumeUrl={resumeVolunteer(volunteer.id).url}
                        subject={volunteer.fullName}
                    />
                )}

                <VolunteerProfileDetails volunteer={volunteer} />
            </div>
        </>
    );
}

VolunteerEdit.layout = {
    breadcrumbs: [{ title: 'Voluntários', href: index() }],
};
