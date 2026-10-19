import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { formatShortDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { edit as editSchedule } from '@/routes/coord/schedules';

export type CoverageRow = {
    eventId: number;
    name: string;
    date: string;
    startsAt: string;
    scheduleId: number | null;
    published: boolean;
    filled: number;
    required: number;
    needsReplacement: number;
    gaps: { role: string; missing: number }[];
};

/**
 * One event's filled vs required places, the roles missing people and a link to its
 * schedule (coverage page and dashboard).
 */
export function CoverageItem({ row }: { row: CoverageRow }) {
    const complete = row.filled >= row.required;
    const percent =
        row.required === 0
            ? 100
            : Math.round((row.filled / row.required) * 100);

    return (
        <li className="space-y-2 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-sm text-muted-foreground">
                        {formatShortDate(row.startsAt)} ·{' '}
                        {formatTime(row.startsAt)}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {row.scheduleId === null ? (
                        <Badge variant="outline">Sem escala</Badge>
                    ) : (
                        !row.published && (
                            <Badge variant="outline">Rascunho</Badge>
                        )
                    )}
                    <Badge variant={complete ? 'secondary' : 'destructive'}>
                        {row.filled}/{row.required}
                        <span className="sr-only"> lugares preenchidos</span>
                    </Badge>
                </div>
            </div>

            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden="true"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        complete ? 'bg-primary' : 'bg-destructive',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>

            {!complete && (
                <p className="text-sm">
                    Em falta:{' '}
                    {row.gaps
                        .map((gap) => `${gap.role} (${gap.missing})`)
                        .join(', ')}
                    {row.needsReplacement > 0 &&
                        ` · ${row.needsReplacement} por substituir`}
                </p>
            )}

            {row.scheduleId !== null && !complete && (
                <Link
                    href={editSchedule(row.scheduleId)}
                    className="inline-flex min-h-11 items-center text-sm font-medium underline-offset-4 hover:underline"
                >
                    Abrir escala
                </Link>
            )}
        </li>
    );
}
