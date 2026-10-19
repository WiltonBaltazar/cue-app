import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { DateBlock } from '@/components/app/date-block';
import { ShiftActions } from '@/components/app/shift-actions';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as teamSchedule } from '@/routes/schedules';

export type Shift = {
    id: number;
    scheduleId: number;
    eventId: number;
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
    canAct: boolean;
    hasActiveSwap: boolean;
};

/**
 * What is unusual about a shift, if anything; a confirmed shift says nothing.
 */
export function shiftNote(shift: Shift): string | null {
    if (shift.eventCancelled) {
        return 'Evento cancelado';
    }

    if (shift.status === 'needs_replacement') {
        return 'À procura de substituto';
    }

    if (shift.hasActiveSwap) {
        return 'Troca pedida';
    }

    return shift.status === 'confirmed' ? null : shift.statusLabel;
}

/**
 * One of the volunteer's shifts as a row of the rundown: date, event and role, and the
 * arrival time on the right, since that is what people look for. Tapping the row
 * shows the event times, place, team link and, with `actions`, what they can do (US4).
 */
export function ShiftCard({
    shift,
    actions = false,
    declineOpen = false,
}: {
    shift: Shift;
    actions?: boolean;
    /** Open the "Não posso ir" confirmation straight away (72 h check push). */
    declineOpen?: boolean;
}) {
    const [open, setOpen] = useState(declineOpen);
    const note = shiftNote(shift);
    const longDate = formatLongDate(`${shift.date}T12:00:00Z`);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className="group flex min-h-16 w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset">
                <DateBlock date={shift.date} />
                <span className="min-w-0 flex-1">
                    <span className="sr-only">{longDate}: </span>
                    <span
                        className={cn(
                            'block truncate font-medium',
                            shift.eventCancelled && 'line-through',
                        )}
                    >
                        {shift.eventName}
                    </span>
                    <span className="block truncate text-sm text-muted-foreground">
                        Função: {shift.role}
                    </span>
                    {note && (
                        <span className="mt-0.5 block text-sm font-medium text-amber-700 dark:text-amber-400">
                            {note}
                        </span>
                    )}
                </span>
                <span className="text-right leading-none">
                    <span className="sr-only">Chegada às </span>
                    <span className="font-display text-2xl font-semibold tabular-nums">
                        {shift.arrivalTime}
                    </span>
                    <span
                        className="mt-1 block text-xs text-muted-foreground"
                        aria-hidden="true"
                    >
                        chegada
                    </span>
                </span>
                <ChevronDown
                    className="size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="px-4 pb-4">
                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-muted/60 p-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Horário</dt>
                        <dd className="font-medium tabular-nums">
                            {shift.startTime}–{shift.endTime}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Local</dt>
                        <dd className="font-medium">{shift.location}</dd>
                    </div>
                </dl>
                <Link
                    href={teamSchedule(shift.scheduleId, {
                        query: { evento: shift.eventId },
                    })}
                    className="mt-2 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
                >
                    Ver a equipa deste evento
                </Link>
                {actions && shift.canAct && (
                    <ShiftActions
                        shift={shift}
                        declineOpen={declineOpen}
                        className="mt-1"
                    />
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}
