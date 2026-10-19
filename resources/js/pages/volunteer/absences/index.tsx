import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import { formatLongDate } from '@/lib/format';
import { destroy, store } from '@/routes/absences';

type Absence = {
    id: number;
    startsOn: string;
    endsOn: string;
    reason: string | null;
};

const longDate = (date: string) => formatLongDate(`${date}T12:00:00Z`);

/**
 * Periods the volunteer can not serve (FR-016). Published shifts inside go to the
 * coordinators for a substitute; the reason is only visible to the volunteer and
 * coordinators.
 */
export default function Absences({
    absences,
    today,
}: {
    absences: Absence[];
    today: string;
}) {
    const form = useForm({ starts_on: today, ends_on: today, reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Ausências" />

            <h1 className="text-2xl font-semibold">Ausências</h1>

            <form
                onSubmit={submit}
                className="mt-4 space-y-4 rounded-xl border p-4"
            >
                <Heading
                    variant="small"
                    title="Vou estar ausente"
                    description="Não será escalado nestes dias. Se já tiver turnos, os coordenadores procuram um substituto."
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label="De"
                        type="date"
                        min={today}
                        value={form.data.starts_on}
                        onChange={(event) =>
                            form.setData('starts_on', event.target.value)
                        }
                        error={form.errors.starts_on}
                    />
                    <TextField
                        label="Até"
                        type="date"
                        min={form.data.starts_on || today}
                        value={form.data.ends_on}
                        onChange={(event) =>
                            form.setData('ends_on', event.target.value)
                        }
                        error={form.errors.ends_on}
                    />
                </div>
                <TextField
                    label="Motivo"
                    optional
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    hint="Só os coordenadores vêem o motivo. Os colegas nunca o vêem."
                    error={form.errors.reason}
                />
                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    Registar ausência
                </Button>
            </form>

            <section aria-labelledby="my-absences" className="mt-6 space-y-3">
                <h2 id="my-absences" className="font-medium">
                    As minhas ausências
                </h2>
                {absences.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Não tem ausências registadas.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {absences.map((absence) => (
                            <li
                                key={absence.id}
                                className="flex items-center justify-between gap-2 rounded-xl border p-4"
                            >
                                <div>
                                    <p className="font-medium first-letter:uppercase">
                                        {absence.startsOn === absence.endsOn
                                            ? longDate(absence.startsOn)
                                            : `${longDate(absence.startsOn)} até ${longDate(absence.endsOn)}`}
                                    </p>
                                    {absence.reason && (
                                        <p className="text-sm text-muted-foreground">
                                            {absence.reason}
                                        </p>
                                    )}
                                </div>
                                <ConfirmDialog
                                    title="Remover esta ausência?"
                                    description="Volta a poder ser escalado nestes dias. Os turnos destes dias voltam a ser seus, excepto os que já têm substituto ou troca em curso."
                                    confirmLabel="Remover"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(destroy(absence.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <Button
                                        variant="ghost"
                                        className="min-h-11"
                                    >
                                        Remover
                                    </Button>
                                </ConfirmDialog>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
