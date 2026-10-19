import { dateParts } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * A calendar leaf for list rows: weekday, day number, month. Decorative for screen
 * readers, so the row must also say the date in words.
 */
export function DateBlock({
    date,
    className,
}: {
    date: string;
    className?: string;
}) {
    const { weekday, day, month } = dateParts(date);

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex w-12 shrink-0 flex-col items-center rounded-lg border bg-background py-1 leading-none',
                className,
            )}
        >
            <span className="text-[0.7rem] text-muted-foreground">
                {weekday}
            </span>
            <span className="mt-0.5 font-display text-2xl font-semibold tabular-nums">
                {day}
            </span>
            <span className="text-[0.7rem] text-muted-foreground">{month}</span>
        </span>
    );
}
