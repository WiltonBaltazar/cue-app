import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CalendarCheck,
    CalendarOff,
    ChevronRight,
    Pause,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { NextShift } from '@/components/app/next-shift';
import type { Team } from '@/components/app/next-shift';
import { PauseCard } from '@/components/app/pause-card';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import { index as absences } from '@/routes/absences';
import { pause, resume } from '@/routes/profile';
import { index as swaps } from '@/routes/swaps';
import { edit as volunteerData } from '@/routes/volunteer/profile';
import { index as myShifts } from '@/routes/volunteer/shifts';

/**
 * Volunteer home: the next shift as a call sheet with its actions (constitution III,
 * ≤ 3 taps), colleagues who need a substitute, then the shifts after it (US4).
 */
export default function VolunteerHome({
    nextShifts,
    nextTeam,
    openSwaps,
    paused,
    pausedUntil,
}: {
    nextShifts: Shift[];
    nextTeam: Team | null;
    openSwaps: number;
    paused: boolean;
    pausedUntil: string | null;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const [next, ...later] = nextShifts;

    return (
        <>
            <Head title="Início" />

            <h1 className="font-display text-3xl font-semibold">
                Olá, {firstName}
            </h1>

            {paused && (
                <div className="mt-4">
                    <PauseCard
                        paused
                        pausedUntil={pausedUntil}
                        pauseUrl={pause().url}
                        resumeUrl={resume().url}
                    />
                </div>
            )}

            <div className="mt-4">
                {next ? (
                    <NextShift shift={next} team={nextTeam} />
                ) : (
                    <section className="rounded-3xl border border-dashed bg-card p-6">
                        <h2 className="font-display text-2xl font-semibold">
                            Sem turnos marcados
                        </h2>
                        <p className="mt-1 text-muted-foreground">
                            Quando uma escala for publicada, o seu próximo turno
                            aparece aqui, com a hora de chegada e a equipa.
                        </p>
                        {!paused && (
                            <Link
                                href={`${volunteerData().url}#disponibilidade`}
                                className="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                            >
                                Rever a minha disponibilidade
                            </Link>
                        )}
                    </section>
                )}
            </div>

            {openSwaps > 0 && (
                <Link
                    href={swaps()}
                    className="mt-3 flex min-h-16 items-center gap-3 rounded-2xl border bg-card px-4 hover:bg-muted/50"
                >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <ArrowLeftRight className="size-5" aria-hidden="true" />
                    </span>
                    <span className="flex-1">
                        <span className="block font-medium">
                            {openSwaps === 1
                                ? 'Um colega precisa de substituto'
                                : `${openSwaps} colegas precisam de substituto`}
                        </span>
                        <span className="block text-sm text-muted-foreground">
                            Em turnos que pode fazer
                        </span>
                    </span>
                    <ChevronRight
                        className="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>
            )}

            <section aria-labelledby="unavailable" className="mt-8">
                <h2
                    id="unavailable"
                    className="font-display text-xl font-semibold"
                >
                    Não vai estar disponível?
                </h2>
                <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                    <AvailabilityLink
                        href={absences().url}
                        icon={CalendarOff}
                        title="Vou estar ausente"
                        description="Marque as datas em que não pode servir. Se já tiver turnos nesses dias, os coordenadores procuram um substituto."
                    />
                    <AvailabilityLink
                        href={`${volunteerData().url}#disponibilidade`}
                        icon={CalendarCheck}
                        title="Os dias em que posso servir"
                        description="Mude os dias e períodos habituais para as próximas escalas."
                    />
                    {!paused && (
                        <AvailabilityLink
                            href={`${absences().url}#pausa`}
                            icon={Pause}
                            title="Fazer uma pausa"
                            description="Deixe de ser escalado durante um tempo: um mês, três meses ou até voltar."
                        />
                    )}
                </ul>
            </section>

            {later.length > 0 && (
                <section aria-labelledby="later-shifts" className="mt-8">
                    <h2
                        id="later-shifts"
                        className="font-display text-xl font-semibold"
                    >
                        A seguir
                    </h2>
                    <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {later.map((shift) => (
                            <li key={shift.id}>
                                <ShiftCard shift={shift} actions />
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {next && (
                <Link
                    href={myShifts()}
                    className="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                >
                    Ver todos os meus turnos
                </Link>
            )}
        </>
    );
}

/** A row of the "Não vai estar disponível?" list. */
function AvailabilityLink({
    href,
    icon: Icon,
    title,
    description,
}: {
    href: string;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <li>
            <Link
                href={href}
                className="flex min-h-16 items-center gap-3 px-4 py-3 hover:bg-muted/50"
            >
                <Icon
                    className="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <span className="flex-1">
                    <span className="block font-medium">{title}</span>
                    <span className="block text-sm text-muted-foreground">
                        {description}
                    </span>
                </span>
                <ChevronRight
                    className="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </li>
    );
}
