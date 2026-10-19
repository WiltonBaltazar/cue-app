import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateBlock } from '@/components/app/date-block';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import { formatDayMonth, formatLongDate, formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { destroy, store } from '@/routes/absences';
import { pause, resume } from '@/routes/profile';

type Absence = {
    id: number;
    startsOn: string;
    endsOn: string;
    reason: string | null;
};

type UpcomingShift = {
    date: string;
    eventName: string;
    role: string;
};

const noon = (date: string) => `${date}T12:00:00Z`;

/** "YYYY-MM-DD" plus whole months, kept inside the month (31 Jan + 1 → 28 Feb). */
function addMonths(date: string, months: number): string {
    const [year, month, day] = date.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months, 1));
    const lastDay = new Date(
        Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0),
    ).getUTCDate();
    target.setUTCDate(Math.min(day, lastDay));

    return target.toISOString().slice(0, 10);
}

/** Segmented choice with large targets; a real radio group for assistive tech. */
function Segmented<T extends string>({
    legend,
    name,
    options,
    value,
    onChange,
}: {
    legend: string;
    name: string;
    options: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
}) {
    return (
        <fieldset>
            <legend className="sr-only">{legend}</legend>
            <div className="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center justify-center rounded-lg text-sm font-medium text-muted-foreground has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                            value === option.value &&
                                'bg-card text-foreground shadow-sm',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="sr-only"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/** Small tappable choice, e.g. a shift day or a pause length. */
function Chip({
    selected,
    onClick,
    children,
}: {
    selected: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onClick}
            className={cn(
                'min-h-11 rounded-full border bg-card px-4 text-sm font-medium hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                selected && 'border-primary bg-primary/10 text-primary',
            )}
        >
            {children}
        </button>
    );
}

/**
 * Everything about not being available, in one tab: block one day or a period
 * (FR-016), or pause for a while (FR-005a). Published shifts inside go to the
 * coordinators for a substitute; the reason is only seen by them (FR-026).
 */
export default function Absences({
    absences,
    today,
    shifts,
    paused,
    pausedUntil,
    canPause,
}: {
    absences: Absence[];
    today: string;
    shifts: UpcomingShift[];
    paused: boolean;
    pausedUntil: string | null;
    canPause: boolean;
}) {
    const [mode, setMode] = useState<'day' | 'range'>('day');
    const form = useForm({ starts_on: today, ends_on: today, reason: '' });
    const endsOn = mode === 'day' ? form.data.starts_on : form.data.ends_on;
    const covered = shifts.filter(
        (shift) => shift.date >= form.data.starts_on && shift.date <= endsOn,
    );

    const [until, setUntil] = useState<string>(addMonths(today, 1));
    const [pauseError, setPauseError] = useState<string | undefined>();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, ends_on: endsOn }));
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setMode('day');
            },
        });
    };

    const pickDay = (date: string) => {
        setMode('day');
        form.setData((data) => ({ ...data, starts_on: date, ends_on: date }));
    };

    return (
        <>
            <Head title="Ausências" />

            <h1 className="font-display text-3xl font-semibold">Ausências</h1>
            <p className="mt-1 text-muted-foreground">
                Diga quando não pode servir. Não é escalado nesses dias.
            </p>

            {paused && (
                <section className="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                    <h2 className="font-display text-xl font-semibold">
                        Está em pausa
                    </h2>
                    <p className="mt-1 text-sm">
                        {pausedUntil
                            ? `Volta a ser escalado a partir de ${formatLongDate(noon(pausedUntil))}.`
                            : 'Sem data de regresso. Não é escalado até voltar.'}
                    </p>
                    <Button
                        className="mt-3 min-h-11"
                        onClick={() =>
                            router.post(
                                resume().url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Voltar a participar
                    </Button>
                </section>
            )}

            <form
                onSubmit={submit}
                aria-labelledby="block-dates"
                className="mt-6 space-y-4 rounded-2xl border bg-card p-4"
            >
                <h2
                    id="block-dates"
                    className="font-display text-xl font-semibold"
                >
                    Bloquear datas
                </h2>

                <Segmented
                    legend="Quanto tempo"
                    name="mode"
                    value={mode}
                    onChange={setMode}
                    options={[
                        { value: 'day', label: 'Um dia' },
                        { value: 'range', label: 'Vários dias' },
                    ]}
                />

                {mode === 'day' && shifts.length > 0 && (
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Os seus próximos turnos
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {[...new Set(shifts.map((s) => s.date))].map(
                                (date) => (
                                    <Chip
                                        key={date}
                                        selected={form.data.starts_on === date}
                                        onClick={() => pickDay(date)}
                                    >
                                        {formatShortDate(noon(date))}
                                    </Chip>
                                ),
                            )}
                        </div>
                    </div>
                )}

                <div
                    className={cn(
                        'grid gap-4',
                        mode === 'range' && 'grid-cols-2',
                    )}
                >
                    <TextField
                        label={mode === 'day' ? 'Dia' : 'De'}
                        type="date"
                        min={today}
                        required
                        value={form.data.starts_on}
                        onChange={(event) => {
                            const value = event.target.value;
                            form.setData((data) => ({
                                ...data,
                                starts_on: value,
                                ends_on:
                                    data.ends_on < value ? value : data.ends_on,
                            }));
                        }}
                        error={form.errors.starts_on}
                    />
                    {mode === 'range' && (
                        <TextField
                            label="Até"
                            type="date"
                            min={form.data.starts_on || today}
                            required
                            value={form.data.ends_on}
                            onChange={(event) =>
                                form.setData('ends_on', event.target.value)
                            }
                            error={form.errors.ends_on}
                        />
                    )}
                </div>

                {covered.length > 0 && (
                    <div
                        className="rounded-xl bg-amber-50 p-3 text-sm dark:bg-amber-950"
                        aria-live="polite"
                    >
                        <p className="font-medium">
                            {`Tem ${covered.length === 1 ? '1 turno' : `${covered.length} turnos`} ${mode === 'day' ? 'neste dia' : 'nestes dias'}:`}
                        </p>
                        <ul className="mt-1 space-y-0.5">
                            {covered.map((shift) => (
                                <li key={`${shift.date}-${shift.eventName}`}>
                                    <span className="first-letter:uppercase">
                                        {formatDayMonth(noon(shift.date))}
                                    </span>
                                    , {shift.eventName}, função: {shift.role}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-1 text-muted-foreground">
                            Os coordenadores são avisados e procuram um
                            substituto.
                        </p>
                    </div>
                )}

                <TextField
                    label="Motivo"
                    optional
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    hint="Só os coordenadores vêem o motivo."
                    error={form.errors.reason}
                />
                <Button
                    type="submit"
                    className="min-h-11 w-full sm:w-auto"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {mode === 'day'
                        ? 'Bloquear este dia'
                        : 'Bloquear estes dias'}
                </Button>
            </form>

            {canPause && !paused && (
                <section
                    id="pausa"
                    aria-labelledby="pause-title"
                    className="mt-4 scroll-mt-20 space-y-4 rounded-2xl border bg-card p-4"
                >
                    <div>
                        <h2
                            id="pause-title"
                            className="font-display text-xl font-semibold"
                        >
                            Fazer uma pausa
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Para uma fase mais ocupada: deixa de ser escalado
                            até à data que escolher.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Chip
                            selected={until === addMonths(today, 1)}
                            onClick={() => setUntil(addMonths(today, 1))}
                        >
                            1 mês
                        </Chip>
                        <Chip
                            selected={until === addMonths(today, 3)}
                            onClick={() => setUntil(addMonths(today, 3))}
                        >
                            3 meses
                        </Chip>
                        <Chip
                            selected={until === ''}
                            onClick={() => setUntil('')}
                        >
                            Sem data de regresso
                        </Chip>
                    </div>
                    <TextField
                        label="Volto a servir em"
                        type="date"
                        optional
                        min={today}
                        value={until}
                        onChange={(event) => setUntil(event.target.value)}
                        hint={
                            until
                                ? `Pausa até ${formatLongDate(noon(until))}.`
                                : 'Volta quando quiser, neste ecrã.'
                        }
                        error={pauseError}
                    />
                    <ConfirmDialog
                        title="Fazer uma pausa?"
                        description={
                            until
                                ? `Não é escalado até ${formatLongDate(noon(until))}. Os turnos já publicados neste período ficam por substituir e os coordenadores são avisados.`
                                : 'Não é escalado até voltar. Os turnos já publicados ficam por substituir e os coordenadores são avisados.'
                        }
                        confirmLabel="Fazer pausa"
                        destructive
                        onConfirm={() =>
                            router.post(
                                pause().url,
                                { until: until || null },
                                {
                                    preserveScroll: true,
                                    onError: (errors) =>
                                        setPauseError(
                                            errors.until ?? errors.volunteer,
                                        ),
                                    onSuccess: () => setPauseError(undefined),
                                },
                            )
                        }
                    >
                        <Button variant="outline" className="min-h-11">
                            Fazer pausa
                        </Button>
                    </ConfirmDialog>
                </section>
            )}

            <section aria-labelledby="my-absences" className="mt-8">
                <h2
                    id="my-absences"
                    className="font-display text-xl font-semibold"
                >
                    Datas bloqueadas
                </h2>
                {absences.length === 0 ? (
                    <p className="mt-2 text-muted-foreground">
                        Nenhuma data bloqueada. Está disponível nos dias
                        habituais.
                    </p>
                ) : (
                    <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {absences.map((absence) => (
                            <li
                                key={absence.id}
                                className="flex items-center gap-3 px-4 py-3"
                            >
                                <DateBlock date={absence.startsOn} />
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium first-letter:uppercase">
                                        {absence.startsOn === absence.endsOn
                                            ? formatDayMonth(
                                                  noon(absence.startsOn),
                                              )
                                            : `${formatShortDate(noon(absence.startsOn))} a ${formatShortDate(noon(absence.endsOn))}`}
                                    </p>
                                    {absence.reason && (
                                        <p className="truncate text-sm text-muted-foreground">
                                            {absence.reason}
                                        </p>
                                    )}
                                </div>
                                <ConfirmDialog
                                    title="Desbloquear estas datas?"
                                    description="Volta a poder ser escalado nestes dias. Os turnos destes dias voltam a ser seus, excepto os que já têm substituto ou troca em curso."
                                    confirmLabel="Desbloquear"
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
                                        Desbloquear
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
