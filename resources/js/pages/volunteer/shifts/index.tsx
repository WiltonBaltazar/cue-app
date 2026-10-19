import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';

export default function MyShifts({ shifts }: { shifts: Shift[] }) {
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
                <ol className="mt-4 space-y-3">
                    {shifts.map((shift) => (
                        <li key={shift.id}>
                            <ShiftCard shift={shift} />
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}
