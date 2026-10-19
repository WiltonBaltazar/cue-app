import { Head, router } from '@inertiajs/react';
import {
    FileDown,
    RefreshCw,
    TriangleAlert,
    UserMinus,
    UserPlus,
    UserRoundPen,
} from 'lucide-react';
import { useState } from 'react';
import { AssignDialog } from '@/components/app/assign-dialog';
import type { AssignTarget } from '@/components/app/assign-dialog';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { destroy as removeAssignment } from '@/routes/coord/assignments';
import { generate, index, pdf, publish } from '@/routes/coord/schedules';

type AssignmentCell = {
    id: number;
    volunteerId: number;
    name: string;
    lockVersion: number;
    source: string;
    overrideNote: string | null;
    conflict: boolean;
};

type PositionRow = {
    id: number;
    role: string;
    quantity: number;
    minLevel: string | null;
    assignments: AssignmentCell[];
};

type EventRow = {
    id: number;
    name: string;
    date: string;
    startTime: string;
    arrivalTime: string;
    cancelled: boolean;
    positions: PositionRow[];
};

type Gap = {
    positionId: number;
    label: string;
    missing: number;
    suggestions: { name: string; reasons: string[] }[];
};

type Props = {
    schedule: {
        id: number;
        startsOn: string;
        endsOn: string;
        status: 'draft' | 'published';
        statusLabel: string;
        publishedAt: string | null;
    };
    events: EventRow[];
    gaps: Gap[];
    volunteersToNotify: number;
};

function eventLabel(event: EventRow, position: PositionRow): string {
    return `${formatShortDate(`${event.date}T12:00:00Z`)} · ${event.name} ${event.startTime} · ${position.role}${position.minLevel ? ` (mín. ${position.minLevel.toLowerCase()})` : ''}`;
}

/**
 * Schedule editor (US3, FR-013, FR-030): table per event on wide screens, stacked cards
 * per event on phones. Gaps are highlighted; manual edits go through the assign dialog.
 */
export default function ScheduleEdit({
    schedule,
    events,
    gaps,
    volunteersToNotify,
}: Props) {
    const [target, setTarget] = useState<AssignTarget | null>(null);
    const published = schedule.status === 'published';
    const gapCount = gaps.reduce((sum, gap) => sum + gap.missing, 0);

    const required = events
        .filter((event) => !event.cancelled)
        .flatMap((event) => event.positions)
        .reduce((sum, position) => sum + position.quantity, 0);
    const filled = events
        .filter((event) => !event.cancelled)
        .flatMap((event) => event.positions)
        .reduce(
            (sum, position) =>
                sum + Math.min(position.quantity, position.assignments.length),
            0,
        );

    const remove = (assignment: AssignmentCell) =>
        router.delete(removeAssignment(assignment.id).url, {
            data: { lock_version: assignment.lockVersion },
            preserveScroll: true,
        });

    const renderPosition = (event: EventRow, position: PositionRow) => {
        const missing = position.quantity - position.assignments.length;

        return (
            <div className="space-y-1">
                {position.assignments.map((assignment) => (
                    <div
                        key={assignment.id}
                        className="flex flex-wrap items-center gap-1"
                    >
                        <span
                            className={cn(
                                'text-sm',
                                assignment.conflict &&
                                    'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {assignment.name}
                        </span>
                        {assignment.conflict && (
                            <Badge
                                variant="outline"
                                className="border-amber-400 text-amber-700"
                            >
                                <TriangleAlert aria-hidden="true" /> conflito de
                                disponibilidade
                            </Badge>
                        )}
                        {assignment.overrideNote && (
                            <Badge
                                variant="outline"
                                title={assignment.overrideNote}
                            >
                                excepção
                            </Badge>
                        )}
                        {!event.cancelled && (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-9"
                                    aria-label={`Substituir ${assignment.name}`}
                                    onClick={() =>
                                        setTarget({
                                            positionId: position.id,
                                            label: eventLabel(event, position),
                                            replacing: {
                                                id: assignment.id,
                                                name: assignment.name,
                                                lockVersion:
                                                    assignment.lockVersion,
                                            },
                                        })
                                    }
                                >
                                    <UserRoundPen
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                                <ConfirmDialog
                                    title={`Retirar ${assignment.name}?`}
                                    description={
                                        published
                                            ? `${assignment.name} será notificado.`
                                            : 'A escala ainda não foi publicada; ninguém é notificado.'
                                    }
                                    confirmLabel="Retirar"
                                    destructive
                                    onConfirm={() => remove(assignment)}
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-9"
                                        aria-label={`Retirar ${assignment.name}`}
                                    >
                                        <UserMinus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </ConfirmDialog>
                            </>
                        )}
                    </div>
                ))}
                {missing > 0 && !event.cancelled && (
                    <Button
                        variant="outline"
                        className="min-h-9 border-dashed border-red-400 text-red-700 dark:text-red-400"
                        onClick={() =>
                            setTarget({
                                positionId: position.id,
                                label: eventLabel(event, position),
                                replacing: null,
                            })
                        }
                    >
                        <UserPlus aria-hidden="true" />
                        {missing === 1
                            ? '1 por preencher'
                            : `${missing} por preencher`}
                    </Button>
                )}
            </div>
        );
    };

    return (
        <>
            <Head title="Editar escala" />

            <div className="space-y-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Escala{' '}
                            {formatDate(`${schedule.startsOn}T12:00:00Z`)} –{' '}
                            {formatDate(`${schedule.endsOn}T12:00:00Z`)}
                        </h1>
                        <p className="text-muted-foreground">
                            <Badge
                                variant={published ? 'default' : 'secondary'}
                            >
                                {schedule.statusLabel}
                            </Badge>{' '}
                            · {filled} de {required} posições preenchidas
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild className="min-h-11">
                            <a href={pdf(schedule.id).url}>
                                <FileDown aria-hidden="true" />
                                PDF
                            </a>
                        </Button>
                        {!published && (
                            <>
                                <ConfirmDialog
                                    title="Gerar de novo?"
                                    description="As atribuições automáticas são refeitas. As que alterou à mão mantêm-se."
                                    confirmLabel="Gerar"
                                    onConfirm={() =>
                                        router.post(
                                            generate(schedule.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <RefreshCw aria-hidden="true" />
                                        Gerar de novo
                                    </Button>
                                </ConfirmDialog>
                                <ConfirmDialog
                                    title="Publicar a escala?"
                                    description={`${volunteersToNotify} voluntários serão notificados e passam a ver os seus turnos.${filled < required ? ` Ainda há ${required - filled} posições por preencher.` : ''}`}
                                    confirmLabel="Publicar"
                                    onConfirm={() =>
                                        router.post(publish(schedule.id).url)
                                    }
                                >
                                    <Button className="min-h-11">
                                        Publicar
                                    </Button>
                                </ConfirmDialog>
                            </>
                        )}
                    </div>
                </header>

                {gaps.length > 0 && !published && (
                    <details className="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/40">
                        <summary className="min-h-11 cursor-pointer content-center font-semibold">
                            {gapCount === 1
                                ? '1 posição por preencher'
                                : `${gapCount} posições por preencher`}{' '}
                            <span className="font-normal text-muted-foreground">
                                — ver os voluntários mais próximos
                            </span>
                        </summary>
                        <ul className="mt-2 space-y-2 text-sm">
                            {gaps.map((gap) => (
                                <li key={gap.positionId}>
                                    <span className="font-medium">
                                        {gap.label}
                                    </span>{' '}
                                    — faltam {gap.missing}.
                                    {gap.suggestions.length > 0 && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            Mais próximos:{' '}
                                            {gap.suggestions
                                                .map(
                                                    (suggestion) =>
                                                        `${suggestion.name} (${suggestion.reasons.join(', ').toLowerCase()})`,
                                                )
                                                .join('; ')}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </details>
                )}

                {events.length === 0 && (
                    <p className="rounded-lg border p-6 text-center text-muted-foreground">
                        Não há eventos neste período. Crie actividades ou
                        eventos primeiro.
                    </p>
                )}

                <ol className="space-y-4">
                    {events.map((event) => (
                        <li
                            key={event.id}
                            className={cn(
                                'rounded-lg border',
                                event.cancelled && 'opacity-60',
                            )}
                        >
                            <h2 className="flex flex-wrap items-center gap-2 border-b bg-muted/40 p-3 font-medium">
                                <span className="first-letter:uppercase">
                                    {formatShortDate(`${event.date}T12:00:00Z`)}
                                </span>
                                · {event.name}
                                <span className="text-sm font-normal text-muted-foreground">
                                    chegada {event.arrivalTime}, início{' '}
                                    {event.startTime}
                                </span>
                                {event.cancelled && (
                                    <Badge variant="secondary">Cancelado</Badge>
                                )}
                            </h2>

                            {/* Phones: one block per position */}
                            <div className="divide-y md:hidden">
                                {event.positions.map((position) => (
                                    <div
                                        key={position.id}
                                        className="space-y-1 p-3"
                                    >
                                        <p className="text-sm font-medium">
                                            {position.role}
                                            {position.minLevel && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · mín.{' '}
                                                    {position.minLevel.toLowerCase()}
                                                </span>
                                            )}
                                        </p>
                                        {renderPosition(event, position)}
                                    </div>
                                ))}
                            </div>

                            {/* Wide screens: table */}
                            <table className="hidden w-full text-sm md:table">
                                <thead className="sr-only">
                                    <tr>
                                        <th>Função</th>
                                        <th>Voluntários</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {event.positions.map((position) => (
                                        <tr key={position.id}>
                                            <th
                                                scope="row"
                                                className="w-64 p-3 text-left align-top font-medium"
                                            >
                                                {position.role}
                                                <span className="block font-normal text-muted-foreground">
                                                    {
                                                        position.assignments
                                                            .length
                                                    }
                                                    /{position.quantity}
                                                    {position.minLevel &&
                                                        ` · mín. ${position.minLevel.toLowerCase()}`}
                                                </span>
                                            </th>
                                            <td className="p-3">
                                                {renderPosition(
                                                    event,
                                                    position,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </li>
                    ))}
                </ol>
            </div>

            <AssignDialog target={target} onClose={() => setTarget(null)} />
        </>
    );
}

ScheduleEdit.layout = {
    breadcrumbs: [{ title: 'Escalas', href: index() }],
};
