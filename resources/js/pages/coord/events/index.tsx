import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/coord/events';
import type { EventSummary } from '@/types';

type Props = {
    month: string;
    previousMonth: string;
    nextMonth: string;
    today: string;
    events: EventSummary[];
};

const WEEKDAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

function monthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Intl.DateTimeFormat('pt-PT', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, monthNumber - 1, 1)));
}

/** Calendar cells for the month, Monday first, padded with nulls. */
function monthCells(month: string): (string | null)[] {
    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(Date.UTC(year, monthNumber - 1, 1));
    const daysInMonth = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
    const leading = (first.getUTCDay() + 6) % 7;
    const cells: (string | null)[] = Array.from(
        { length: leading },
        () => null,
    );

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(`${month}-${String(day).padStart(2, '0')}`);
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    return cells;
}

function EventChip({ event }: { event: EventSummary }) {
    return (
        <Link
            href={show(event.id)}
            className={cn(
                'block truncate rounded px-1.5 py-1 text-xs hover:underline',
                event.status === 'cancelled'
                    ? 'bg-muted text-muted-foreground line-through'
                    : event.type === 'special'
                      ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                      : 'bg-primary/10',
            )}
        >
            {event.startTime} {event.name}
        </Link>
    );
}

/**
 * Month grid on wide screens, agenda list on phones (FR-030).
 */
export default function EventsIndex({
    month,
    previousMonth,
    nextMonth,
    today,
    events,
}: Props) {
    const byDate = new Map<string, EventSummary[]>();

    for (const event of events) {
        byDate.set(event.date, [...(byDate.get(event.date) ?? []), event]);
    }

    return (
        <>
            <Head title="Eventos" />

            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Eventos</h1>
                    <Button asChild className="min-h-11">
                        <Link href={create()}>
                            <Plus aria-hidden="true" />
                            Evento extraordinário
                        </Link>
                    </Button>
                </div>

                <nav aria-label="Mês" className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        asChild
                        className="size-11"
                    >
                        <Link
                            href={index({ query: { mes: previousMonth } })}
                            aria-label="Mês anterior"
                        >
                            <ChevronLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <h2
                        className="min-w-44 text-center text-lg font-medium first-letter:uppercase"
                        aria-live="polite"
                    >
                        {monthLabel(month)}
                    </h2>
                    <Button
                        variant="outline"
                        size="icon"
                        asChild
                        className="size-11"
                    >
                        <Link
                            href={index({ query: { mes: nextMonth } })}
                            aria-label="Mês seguinte"
                        >
                            <ChevronRight aria-hidden="true" />
                        </Link>
                    </Button>
                </nav>

                {/* Phones: agenda list */}
                <ol className="space-y-2 md:hidden" aria-label="Eventos do mês">
                    {events.length === 0 && (
                        <li className="rounded-lg border p-4 text-center text-sm text-muted-foreground">
                            Não há eventos neste mês.
                        </li>
                    )}
                    {events.map((event) => (
                        <li key={event.id}>
                            <Link
                                href={show(event.id)}
                                className="flex min-h-11 items-center gap-3 rounded-lg border p-3"
                            >
                                <span className="w-20 shrink-0 text-sm font-medium">
                                    {formatShortDate(`${event.date}T12:00:00Z`)}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span
                                        className={cn(
                                            'block truncate font-medium',
                                            event.status === 'cancelled' &&
                                                'line-through',
                                        )}
                                    >
                                        {event.name}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {event.startTime}–{event.endTime} ·{' '}
                                        {event.requiredPeople} pessoas
                                    </span>
                                </span>
                                {event.status === 'cancelled' && (
                                    <Badge variant="secondary">Cancelado</Badge>
                                )}
                            </Link>
                        </li>
                    ))}
                </ol>

                {/* Wide screens: month grid */}
                <div className="hidden md:block">
                    <div
                        className="grid grid-cols-7 border-b text-sm font-medium text-muted-foreground"
                        aria-hidden="true"
                    >
                        {WEEKDAYS.map((day) => (
                            <div key={day} className="p-2">
                                {day}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {monthCells(month).map((date, index) => (
                            <div
                                key={date ?? `empty-${index}`}
                                className={cn(
                                    'min-h-28 border-r border-b p-1',
                                    index % 7 === 0 && 'border-l',
                                )}
                            >
                                {date && (
                                    <>
                                        <div className="flex items-center justify-between">
                                            <span
                                                className={cn(
                                                    'flex size-7 items-center justify-center rounded-full text-sm',
                                                    date === today &&
                                                        'bg-primary font-semibold text-primary-foreground',
                                                )}
                                            >
                                                {Number(date.slice(8))}
                                            </span>
                                            {date >= today && (
                                                <Link
                                                    href={create({
                                                        query: { data: date },
                                                    })}
                                                    className="rounded p-1 text-muted-foreground opacity-0 hover:bg-muted focus:opacity-100 [div:hover>&]:opacity-100"
                                                    aria-label={`Novo evento a ${date}`}
                                                >
                                                    <Plus
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            )}
                                        </div>
                                        <div className="mt-1 space-y-1">
                                            {(byDate.get(date) ?? []).map(
                                                (event) => (
                                                    <EventChip
                                                        key={event.id}
                                                        event={event}
                                                    />
                                                ),
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Eventos', href: index() }],
};
