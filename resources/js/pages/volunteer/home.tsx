import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import { index as myShifts } from '@/routes/volunteer/shifts';

/**
 * Volunteer home: the next shifts first (constitution III, ≤ 3 taps). Declining a shift
 * and open swaps arrive with User Story 4.
 */
export default function VolunteerHome({ nextShifts }: { nextShifts: Shift[] }) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Início" />

            <h1 className="text-2xl font-semibold">Olá, {firstName}</h1>

            <section aria-labelledby="next-shifts" className="mt-6 space-y-3">
                <h2 id="next-shifts" className="font-medium">
                    Próximos turnos
                </h2>

                {nextShifts.length === 0 ? (
                    <div className="rounded-xl border p-6 text-center">
                        <CalendarDays
                            className="mx-auto size-10 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">Ainda não tem turnos</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Quando a escala for publicada, os seus próximos
                            turnos aparecem aqui.
                        </p>
                    </div>
                ) : (
                    <>
                        {nextShifts.map((shift) => (
                            <ShiftCard key={shift.id} shift={shift} />
                        ))}
                        <Link
                            href={myShifts()}
                            className="inline-flex min-h-11 items-center text-sm underline underline-offset-4"
                        >
                            Ver todos os meus turnos
                        </Link>
                    </>
                )}
            </section>
        </>
    );
}
