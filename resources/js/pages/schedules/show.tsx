import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    schedule: { id: number; startsOn: string; endsOn: string };
    events: {
        id: number;
        name: string;
        date: string;
        arrivalTime: string;
        startTime: string;
        cancelled: boolean;
        positions: {
            role: string;
            people: { name: string; isMe: boolean }[];
        }[];
    }[];
};

/**
 * The published team schedule for volunteers (FR-027): colleagues as "Nome A." with their
 * role, the viewer's own shifts highlighted, no contact data.
 */
export default function TeamSchedule({ schedule, events }: Props) {
    return (
        <>
            <Head title="Escala da equipa" />

            <h1 className="text-2xl font-semibold">Escala da equipa</h1>
            <p className="text-muted-foreground">
                {formatDate(`${schedule.startsOn}T12:00:00Z`)} –{' '}
                {formatDate(`${schedule.endsOn}T12:00:00Z`)}
            </p>

            <ol className="mt-4 space-y-3">
                {events.map((event) => {
                    const mine = event.positions.some((position) =>
                        position.people.some((person) => person.isMe),
                    );

                    return (
                        <li
                            key={event.id}
                            className={cn(
                                'rounded-xl border p-4',
                                mine && 'border-primary ring-1 ring-primary',
                                event.cancelled && 'opacity-60',
                            )}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="font-semibold first-letter:uppercase">
                                    {formatShortDate(`${event.date}T12:00:00Z`)}{' '}
                                    · {event.name}
                                </h2>
                                {mine && <Badge>Está escalado</Badge>}
                                {event.cancelled && (
                                    <Badge variant="secondary">Cancelado</Badge>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Chegada {event.arrivalTime} · início{' '}
                                {event.startTime}
                            </p>
                            <dl className="mt-2 grid gap-1 text-sm">
                                {event.positions.map((position) => (
                                    <div
                                        key={position.role}
                                        className="grid grid-cols-[minmax(0,10rem)_1fr] gap-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {position.role}
                                        </dt>
                                        <dd>
                                            {position.people.length === 0
                                                ? '—'
                                                : position.people.map(
                                                      (person, index) => (
                                                          <span
                                                              key={index}
                                                              className={cn(
                                                                  person.isMe &&
                                                                      'font-semibold',
                                                              )}
                                                          >
                                                              {index > 0 &&
                                                                  ', '}
                                                              {person.isMe
                                                                  ? `${person.name} (eu)`
                                                                  : person.name}
                                                          </span>
                                                      ),
                                                  )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </li>
                    );
                })}
            </ol>
        </>
    );
}
