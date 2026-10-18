import { Head, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

/**
 * Volunteer home. Next shifts, one-tap decline and open swaps arrive with
 * User Stories 3 and 4; until then it shows the empty state (FR-038).
 */
export default function VolunteerHome() {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Início" />

            <h1 className="text-2xl font-semibold">Olá, {firstName}</h1>

            <section
                aria-labelledby="next-shifts"
                className="mt-6 rounded-xl border p-6 text-center"
            >
                <CalendarDays
                    className="mx-auto size-10 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 id="next-shifts" className="mt-3 font-medium">
                    Ainda não tem turnos
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Quando a escala for publicada, os seus próximos turnos
                    aparecem aqui.
                </p>
            </section>
        </>
    );
}
