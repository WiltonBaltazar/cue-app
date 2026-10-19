import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { edit, index } from '@/routes/coord/volunteers';

type Stats = {
    shiftsDone: number;
    upcoming: number;
    declines: number;
    replaced: number;
    swapsRequested: number;
    swapsTaken: number;
    absences: number;
};

type Shift = {
    id: number;
    date: string;
    shift: string;
    status: string;
};

const STAT_LABELS: { key: keyof Stats; label: string }[] = [
    { key: 'shiftsDone', label: 'Turnos feitos' },
    { key: 'upcoming', label: 'Turnos agendados' },
    { key: 'declines', label: 'Recusas' },
    { key: 'replaced', label: 'Por substituir (ausência ou pausa)' },
    { key: 'swapsRequested', label: 'Trocas pedidas' },
    { key: 'swapsTaken', label: 'Turnos assumidos por troca' },
    { key: 'absences', label: 'Ausências registadas' },
];

/**
 * One volunteer's participation and real vs declared frequency (US5).
 */
export default function VolunteerHistory({
    volunteer,
    stats,
    frequency,
    shifts,
}: {
    volunteer: {
        id: number;
        name: string;
        status: string;
        declaredFrequency: string;
    };
    stats: Stats;
    frequency: { perMonth: number; months: number };
    shifts: Shift[];
}) {
    return (
        <>
            <Head title={`Histórico — ${volunteer.name}`} />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">{volunteer.name}</h1>
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge>{volunteer.status}</Badge>
                        <Link
                            href={edit(volunteer.id)}
                            className="text-sm underline-offset-4 hover:underline"
                        >
                            Ver perfil
                        </Link>
                    </div>
                </header>

                <section
                    aria-labelledby="frequency"
                    className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                >
                    <h2 id="frequency" className="sr-only">
                        Frequência
                    </h2>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Frequência real
                        </p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {frequency.perMonth.toLocaleString('pt-PT')} por mês
                        </p>
                        <p className="text-sm text-muted-foreground">
                            média dos últimos {frequency.months}{' '}
                            {frequency.months === 1 ? 'mês' : 'meses'}
                        </p>
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Frequência declarada
                        </p>
                        <p className="text-2xl font-semibold">
                            {volunteer.declaredFrequency}
                        </p>
                    </div>
                </section>

                <section aria-labelledby="totals" className="space-y-3">
                    <h2 id="totals" className="font-medium">
                        Participação
                    </h2>
                    <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {STAT_LABELS.map(({ key, label }) => (
                            <div key={key} className="rounded-lg border p-3">
                                <dt className="text-sm text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-xl font-semibold tabular-nums">
                                    {stats[key]}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section aria-labelledby="shifts" className="space-y-3">
                    <h2 id="shifts" className="font-medium">
                        Turnos
                    </h2>
                    {shifts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Ainda não foi escalado em nenhuma escala publicada.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {shifts.map((shift) => (
                                <li
                                    key={shift.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-3"
                                >
                                    <span>{shift.shift}</span>
                                    <Badge
                                        variant={
                                            shift.status === 'Feito'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {shift.status}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

VolunteerHistory.layout = {
    breadcrumbs: [{ title: 'Voluntários', href: index() }],
};
