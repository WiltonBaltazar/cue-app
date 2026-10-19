import { Head, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';

export default function MyShifts({ shifts }: { shifts: Shift[] }) {
    // "Não posso ir" on the 72 h check push lands here with ?recusar={id}.
    const { url } = usePage();
    const declineId = Number(
        new URLSearchParams(url.split('?')[1] ?? '').get('recusar'),
    );

    return (
        <>
            <Head title="A minha escala" />

            <h1 className="text-2xl font-semibold">A minha escala</h1>

            {shifts.length === 0 ? (
                <section className="mt-6 rounded-xl border p-6 text-center">
                    <CalendarDays
                        className="mx-auto size-10 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h2 className="mt-3 font-medium">Ainda não tem turnos</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Quando a escala for publicada, os seus turnos aparecem
                        aqui.
                    </p>
                </section>
            ) : (
                <>
                    <h2 className="sr-only">Próximos turnos</h2>
                    <ol className="mt-4 space-y-3">
                        {shifts.map((shift) => (
                            <li key={shift.id}>
                                <ShiftCard
                                    shift={shift}
                                    actions
                                    declineOpen={shift.id === declineId}
                                />
                            </li>
                        ))}
                    </ol>
                </>
            )}
        </>
    );
}
