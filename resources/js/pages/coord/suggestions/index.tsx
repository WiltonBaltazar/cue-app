import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/coord/suggestions';
import { edit as editVolunteer } from '@/routes/coord/volunteers';

type Status = 'new' | 'in_progress' | 'done' | 'archived';

type Suggestion = {
    volunteerId: number;
    volunteer: string;
    volunteerStatus: string;
    text: string;
    submittedAt: string | null;
    note: string | null;
    handledBy: string | null;
    handledAt: string | null;
};

type Props = {
    status: Status;
    statuses: { value: Status; label: string; count: number }[];
    suggestions: Suggestion[];
};

const tabClass = (active: boolean) =>
    cn(
        'inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium',
        active ? 'border-primary bg-muted' : 'text-muted-foreground',
    );

/** The next steps offered from each list, most likely first. */
const actions: Record<Status, { to: Status; label: string }[]> = {
    new: [
        { to: 'in_progress', label: 'Vamos tratar' },
        { to: 'done', label: 'Já está feita' },
        { to: 'archived', label: 'Arquivar' },
    ],
    in_progress: [
        { to: 'done', label: 'Marcar como feita' },
        { to: 'archived', label: 'Arquivar' },
    ],
    done: [{ to: 'in_progress', label: 'Reabrir' }],
    archived: [{ to: 'new', label: 'Repor como nova' }],
};

const emptyText: Record<Status, string> = {
    new: 'Não há sugestões novas.',
    in_progress: 'Nenhuma sugestão em curso.',
    done: 'Ainda nenhuma sugestão foi marcada como feita.',
    archived: 'Nenhuma sugestão arquivada.',
};

/**
 * The suggestions volunteers left in their registration, for coordinators to follow up.
 */
export default function Suggestions({ status, statuses, suggestions }: Props) {
    return (
        <>
            <Head title="Sugestões" />

            <div className="space-y-6 p-4 lg:max-w-3xl">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold">Sugestões</h1>
                    <p className="text-muted-foreground">
                        O que os voluntários sugeriram para a equipa no cadastro
                        ou nos seus dados.
                    </p>
                </div>

                <nav aria-label="Estado" className="flex flex-wrap gap-2">
                    {statuses.map((option) => (
                        <Link
                            key={option.value}
                            href={index({ query: { estado: option.value } })}
                            className={tabClass(option.value === status)}
                            aria-current={
                                option.value === status ? 'page' : undefined
                            }
                            preserveScroll
                        >
                            {option.label}
                            <span className="tabular-nums">{option.count}</span>
                        </Link>
                    ))}
                </nav>

                {suggestions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {emptyText[status]}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {suggestions.map((suggestion) => (
                            <SuggestionCard
                                key={suggestion.volunteerId}
                                suggestion={suggestion}
                                status={status}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function SuggestionCard({
    suggestion,
    status,
}: {
    suggestion: Suggestion;
    status: Status;
}) {
    const form = useForm<{ status: Status; note: string }>({
        status,
        note: suggestion.note ?? '',
    });
    const noteId = `note-${suggestion.volunteerId}`;

    const send = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const submitter = (event.nativeEvent as SubmitEvent)
            .submitter as HTMLButtonElement | null;
        const to = (submitter?.value as Status | undefined) ?? status;

        form.transform((data) => ({ ...data, status: to }));
        form.put(update(suggestion.volunteerId).url, {
            preserveScroll: true,
        });
    };

    return (
        <li className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <Link
                    href={editVolunteer(suggestion.volunteerId)}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {suggestion.volunteer}
                </Link>
                <span className="text-sm text-muted-foreground">
                    {suggestion.volunteerStatus}
                    {suggestion.submittedAt &&
                        ` · cadastro em ${formatDate(suggestion.submittedAt)}`}
                </span>
            </div>

            <blockquote className="border-l-2 pl-3 whitespace-pre-line">
                {suggestion.text}
            </blockquote>

            <form onSubmit={send} className="space-y-3">
                <div className="grid gap-2">
                    <Label htmlFor={noteId}>Nota da coordenação</Label>
                    <Textarea
                        id={noteId}
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        placeholder="O que foi decidido ou feito. Só os coordenadores vêem."
                        maxLength={2000}
                    />
                    <InputError message={form.errors.note} />
                    {suggestion.handledBy && suggestion.handledAt && (
                        <p className="text-sm text-muted-foreground">
                            Actualizada por {suggestion.handledBy} em{' '}
                            {formatDate(suggestion.handledAt)}
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    {actions[status].map((action, position) => (
                        <Button
                            key={action.to}
                            type="submit"
                            value={action.to}
                            variant={position === 0 ? 'default' : 'outline'}
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {form.processing && position === 0 && <Spinner />}
                            {action.label}
                        </Button>
                    ))}
                    <Button
                        type="submit"
                        value={status}
                        variant="ghost"
                        className="min-h-11"
                        disabled={form.processing || !form.isDirty}
                    >
                        Guardar nota
                    </Button>
                </div>
            </form>
        </li>
    );
}

Suggestions.layout = {
    breadcrumbs: [{ title: 'Sugestões', href: index() }],
};
