import { Link, router } from '@inertiajs/react';
import { Clock, MapPin } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { SwapRequestDialog } from '@/components/app/swap-request-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatLongDate } from '@/lib/format';
import { show as teamSchedule } from '@/routes/schedules';
import { decline } from '@/routes/volunteer/shifts';

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
 * One of the volunteer's own shifts: date, role and, most importantly, arrival time.
 * With `actions`, also "Não posso ir" and "Pedir troca" (US4).
 */
export function ShiftCard({
    shift,
    actions = false,
}: {
    shift: Shift;
    actions?: boolean;
}) {
    const [swapOpen, setSwapOpen] = useState(false);
    const label = `${shift.eventName} · ${shift.role} · ${formatLongDate(`${shift.date}T12:00:00Z`)}`;

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
                    <>
                        {shift.status !== 'confirmed' && (
                            <Badge variant="outline">{shift.statusLabel}</Badge>
                        )}
                        {shift.hasActiveSwap && (
                            <Badge variant="outline">Troca pedida</Badge>
                        )}
                    </>
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
                href={teamSchedule(shift.scheduleId, {
                    query: { evento: shift.eventId },
                })}
                className="mt-3 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
            >
                Ver equipa deste evento
            </Link>
            {actions && shift.canAct && (
                <div className="mt-3 flex flex-wrap gap-2 border-t pt-3">
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setSwapOpen(true)}
                    >
                        Pedir troca
                    </Button>
                    <ConfirmDialog
                        title="Não pode ir?"
                        description={`${label}. Os coordenadores são avisados e o turno é oferecido aos colegas que podem assumir.`}
                        confirmLabel="Não posso ir"
                        destructive
                        onConfirm={() =>
                            router.post(
                                decline(shift.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Button variant="ghost" className="min-h-11">
                            Não posso ir
                        </Button>
                    </ConfirmDialog>
                    <SwapRequestDialog
                        assignmentId={shift.id}
                        shiftLabel={label}
                        open={swapOpen}
                        onOpenChange={setSwapOpen}
                    />
                </div>
            )}
        </article>
    );
}
