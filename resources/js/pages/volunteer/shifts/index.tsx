import { Head, Link, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import { formatDate, formatMonth } from '@/lib/format';
import { index as absences } from '@/routes/absences';
import { pdf as schedulePdf, show as showSchedule } from '@/routes/schedules';
import { edit as volunteerData } from '@/routes/volunteer/profile';

/** Shifts grouped by month, in date order, so the list reads like a calendar. */
function byMonth(shifts: Shift[]): { month: string; shifts: Shift[] }[] {
    const groups = new Map<string, Shift[]>();

    for (const shift of shifts) {
        const key = shift.date.slice(0, 7);
        groups.set(key, [...(groups.get(key) ?? []), shift]);
    }

    return [...groups.entries()].map(([key, items]) => ({
        month: formatMonth(`${key}-01`),
        shifts: items,
    }));
}

type PublishedSchedule = { id: number; startsOn: string; endsOn: string };

export default function MyShifts({
    shifts,
    schedules,
}: {
    shifts: Shift[];
    schedules: PublishedSchedule[];
}) {
    // "Não posso ir" on the 72 h check push lands here with ?recusar={id}.
    const { url } = usePage();
    const declineId = Number(
        new URLSearchParams(url.split('?')[1] ?? '').get('recusar'),
    );

    return (
        <>
            <Head title="A minha escala" />

            <h1 className="font-display text-3xl font-semibold">
                A minha escala
            </h1>

            {shifts.length === 0 ? (
                <section className="mt-4 rounded-3xl border border-dashed bg-card p-6">
                    <h2 className="font-display text-2xl font-semibold">
                        Ainda não tem turnos
                    </h2>
                    <p className="mt-1 text-muted-foreground">
                        Quando a escala for publicada, os seus turnos aparecem
                        aqui por ordem de data.
                    </p>
                    <Link
                        href={`${volunteerData().url}#disponibilidade`}
                        className="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                    >
                        Rever a minha disponibilidade
                    </Link>
                </section>
            ) : (
                <p className="mt-1 text-muted-foreground">
                    Toque num turno para ver o horário, o local e o que pode
                    fazer. Vai faltar vários dias?{' '}
                    <Link
                        href={absences()}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        Marque uma ausência
                    </Link>
                    .
                </p>
            )}

            {schedules.length > 0 && (
                <section className="mt-6">
                    <h2 className="font-display text-xl font-semibold">
                        Escala completa
                    </h2>
                    <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {schedules.map((schedule) => (
                            <li
                                key={schedule.id}
                                className="flex flex-wrap items-center justify-between gap-2 p-4"
                            >
                                <Link
                                    href={showSchedule(schedule.id)}
                                    className="min-h-11 content-center font-medium underline-offset-4 hover:underline"
                                >
                                    {formatDate(
                                        `${schedule.startsOn}T12:00:00Z`,
                                    )}{' '}
                                    –{' '}
                                    {formatDate(`${schedule.endsOn}T12:00:00Z`)}
                                </Link>
                                <a
                                    href={schedulePdf(schedule.id).url}
                                    className="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary underline underline-offset-4"
                                >
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Descarregar PDF
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {byMonth(shifts).map((group) => (
                <section key={group.month} className="mt-6">
                    <h2 className="font-display text-xl font-semibold first-letter:uppercase">
                        {group.month}
                    </h2>
                    <ol className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {group.shifts.map((shift) => (
                            <li key={shift.id}>
                                <ShiftCard
                                    shift={shift}
                                    actions
                                    declineOpen={shift.id === declineId}
                                />
                            </li>
                        ))}
                    </ol>
                </section>
            ))}
        </>
    );
}
