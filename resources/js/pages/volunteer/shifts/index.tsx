import { Head, Link, usePage } from '@inertiajs/react';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import { formatMonth } from '@/lib/format';
import { index as absences } from '@/routes/absences';
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

export default function MyShifts({ shifts }: { shifts: Shift[] }) {
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
