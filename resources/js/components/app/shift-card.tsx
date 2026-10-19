import { Link } from '@inertiajs/react';
import { Clock, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatLongDate } from '@/lib/format';
import { show as teamSchedule } from '@/routes/schedules';

export type Shift = {
    id: number;
    scheduleId: number;
    date: string;
    eventName: string;
    role: string;
    arrivalTime: string;
    startTime: string;
    endTime: string;
    location: string;
    status: string;
    statusLabel: string;
    eventCancelled: boolean;
};

/**
 * One of the volunteer's own shifts: date, role and, most importantly, arrival time.
 */
export function ShiftCard({ shift }: { shift: Shift }) {
    return (
        <article className="rounded-xl border p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-sm text-muted-foreground first-letter:uppercase">
                        {formatLongDate(`${shift.date}T12:00:00Z`)}
                    </p>
                    <h3
                        className={`text-lg font-semibold ${shift.eventCancelled ? 'line-through' : ''}`}
                    >
                        {shift.eventName}
                    </h3>
                    <p className="font-medium">{shift.role}</p>
                </div>
                {shift.eventCancelled ? (
                    <Badge variant="secondary">Cancelado</Badge>
                ) : (
                    shift.status !== 'confirmed' && (
                        <Badge variant="outline">{shift.statusLabel}</Badge>
                    )
                )}
            </div>
            <ul className="mt-3 space-y-1 text-sm">
                <li className="flex items-center gap-2">
                    <Clock
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span>
                        Chegada <strong>{shift.arrivalTime}</strong> · evento{' '}
                        {shift.startTime}–{shift.endTime}
                    </span>
                </li>
                <li className="flex items-center gap-2">
                    <MapPin
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {shift.location}
                </li>
            </ul>
            <Link
                href={teamSchedule(shift.scheduleId)}
                className="mt-3 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
            >
                Ver escala da equipa
            </Link>
        </article>
    );
}
