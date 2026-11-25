import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import { formatDate } from '@/lib/format';
import { destroy, store } from '@/routes/coord/volunteers/absences';

export type AbsenceRow = {
    id: number;
    startsOn: string;
    endsOn: string;
    byCoordinator: boolean;
    removable: boolean;
};

/** "02/11/2026" or "02/11/2026 a 08/11/2026". */
function period(startsOn: string, endsOn: string): string {
    const from = formatDate(`${startsOn}T12:00:00Z`);

    return startsOn === endsOn
        ? from
        : `${from} a ${formatDate(`${endsOn}T12:00:00Z`)}`;
}

/**
 * A volunteer's absences by date, never why, and the pause they are on. A coordinator can
 * add one for someone who says they can not come.
 */
export function VolunteerAbsences({
    volunteerId,
    upcoming,
    past,
    pause,
    today,
    canRegister,
}: {
    volunteerId: number;
    upcoming: AbsenceRow[];
    past: AbsenceRow[];
    pause: { since: string | null; until: string | null } | null;
    today: string;
    canRegister: boolean;
}) {
    return (
        <section aria-labelledby="absences" className="space-y-4">
            <h2 id="absences" className="font-medium">
                Ausências e pausas
            </h2>

            {pause && (
                <p className="rounded-lg border bg-muted/50 p-3 text-sm">
                    {pause.since
                        ? `Em pausa desde ${formatDate(`${pause.since}T12:00:00Z`)}, `
                        : 'Em pausa, '}
                    {pause.until
                        ? `volta a ${formatDate(`${pause.until}T12:00:00Z`)}.`
                        : 'sem data de regresso.'}
                </p>
            )}

            <AbsenceList
                title="Em curso e futuras"
                empty="Não há ausências marcadas."
                volunteerId={volunteerId}
                rows={upcoming}
            />
            {past.length > 0 && (
                <AbsenceList
                    title="Passadas"
                    empty=""
                    volunteerId={volunteerId}
                    rows={past}
                />
            )}

            {canRegister && (
                <AbsenceForm volunteerId={volunteerId} today={today} />
            )}
        </section>
    );
}

function AbsenceList({
    title,
    empty,
    volunteerId,
    rows,
}: {
    title: string;
    empty: string;
    volunteerId: number;
    rows: AbsenceRow[];
}) {
    const [removing, setRemoving] = useState(false);

    return (
        <div className="space-y-2">
            <h3 className="text-sm text-muted-foreground">{title}</h3>
            {rows.length === 0 ? (
                <p className="text-sm">{empty}</p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {rows.map((row) => (
                        <li
                            key={row.id}
                            className="flex min-h-11 flex-wrap items-center justify-between gap-2 px-3 py-2"
                        >
                            <span className="flex flex-wrap items-center gap-2 tabular-nums">
                                {period(row.startsOn, row.endsOn)}
                                {row.byCoordinator && (
                                    <Badge variant="outline">
                                        Registada por coordenador
                                    </Badge>
                                )}
                            </span>
                            {row.removable && (
                                <ConfirmDialog
                                    title="Remover esta ausência?"
                                    description={`${period(row.startsOn, row.endsOn)}. Os turnos que ainda estiverem por substituir voltam ao voluntário.`}
                                    confirmLabel="Remover"
                                    destructive
                                    processing={removing}
                                    onConfirm={() =>
                                        router.delete(
                                            destroy({
                                                volunteer: volunteerId,
                                                absence: row.id,
                                            }).url,
                                            {
                                                preserveScroll: true,
                                                onStart: () =>
                                                    setRemoving(true),
                                                onFinish: () =>
                                                    setRemoving(false),
                                            },
                                        )
                                    }
                                >
                                    <Button
                                        variant="ghost"
                                        className="min-h-11"
                                    >
                                        Remover
                                        <span className="sr-only">
                                            {' '}
                                            ausência de{' '}
                                            {period(row.startsOn, row.endsOn)}
                                        </span>
                                    </Button>
                                </ConfirmDialog>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function AbsenceForm({
    volunteerId,
    today,
}: {
    volunteerId: number;
    today: string;
}) {
    const form = useForm({ starts_on: today, ends_on: today });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store(volunteerId).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-lg border p-4">
            <div>
                <h3 className="font-medium">Registar ausência</h3>
                <p className="text-sm text-muted-foreground">
                    Para quem avisou que não pode. Os turnos publicados nessas
                    datas ficam por substituir.
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    label="De"
                    type="date"
                    min={today}
                    value={form.data.starts_on}
                    onChange={(event) => {
                        const startsOn = event.target.value;
                        form.setData((data) => ({
                            starts_on: startsOn,
                            ends_on:
                                data.ends_on < startsOn
                                    ? startsOn
                                    : data.ends_on,
                        }));
                    }}
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
            <Button
                type="submit"
                className="min-h-11"
                disabled={form.processing}
            >
                {form.processing && <Spinner />}
                Registar ausência
            </Button>
        </form>
    );
}
