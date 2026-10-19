import { Link } from '@inertiajs/react';
import { ShiftActions } from '@/components/app/shift-actions';
import { shiftNote } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import { daysUntil, formatDayMonth, relativeDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as teamSchedule } from '@/routes/schedules';

export type Team = {
    people: { name: string; role: string; isMe: boolean }[];
    needsReplacement: number;
};

/**
 * The next shift as a call sheet: when it is, the arrival time set large (the one
 * thing nobody may miss), the event, who else is on the team, and the actions.
 * The gold tally light is lit on the day of the shift, as a camera's is when live.
 */
export function NextShift({
    shift,
    team,
}: {
    shift: Shift;
    team: Team | null;
}) {
    const isToday = daysUntil(shift.date) === 0;
    const note = shiftNote(shift);
    const colleagues = team?.people.filter((person) => !person.isMe) ?? [];
    // Grouped by role, so a big team stays a few short lines.
    const byRole = [
        ...colleagues
            .reduce((groups, person) => {
                groups.set(person.role, [
                    ...(groups.get(person.role) ?? []),
                    person.name,
                ]);

                return groups;
            }, new Map<string, string[]>())
            .entries(),
    ];

    return (
        <section
            aria-labelledby="next-shift-title"
            className="rounded-3xl bg-slate p-5 text-slate-foreground shadow-[0_1px_0_0_var(--slate-line)_inset] sm:p-6"
        >
            <p className="flex items-center gap-2.5 text-sm text-slate-muted">
                <span
                    aria-hidden="true"
                    className={cn(
                        'size-2.5 rounded-full',
                        isToday
                            ? 'tally-live bg-tally'
                            : 'border border-tally/70 bg-transparent',
                    )}
                />
                <span>
                    <strong className="font-semibold text-slate-foreground">
                        {relativeDay(shift.date)}
                    </strong>
                    {', '}
                    {formatDayMonth(`${shift.date}T12:00:00Z`)}
                </span>
            </p>

            <p className="mt-5 text-sm text-slate-muted">Chegada às</p>
            <p className="font-display text-[5.5rem] leading-[0.85] font-semibold tracking-tight tabular-nums">
                {shift.arrivalTime}
            </p>

            <h2
                id="next-shift-title"
                className={cn(
                    'mt-5 font-display text-2xl leading-tight font-semibold',
                    shift.eventCancelled && 'line-through',
                )}
            >
                {shift.eventName}
            </h2>
            <p className="text-slate-muted">
                Função:{' '}
                <span className="text-slate-foreground">{shift.role}</span>
            </p>
            {note && (
                <p className="mt-2 inline-flex rounded-full bg-tally px-3 py-1 text-sm font-medium text-slate">
                    {note}
                </p>
            )}

            <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-slate-line pt-4 text-sm">
                <div>
                    <dt className="text-slate-muted">Horário</dt>
                    <dd className="mt-0.5 font-medium tabular-nums">
                        {shift.startTime}–{shift.endTime}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-muted">Local</dt>
                    <dd className="mt-0.5 font-medium">{shift.location}</dd>
                </div>
            </dl>

            {team && (
                <div className="mt-5 border-t border-slate-line pt-4">
                    <h3 className="text-sm text-slate-muted">
                        Com quem vai servir
                    </h3>
                    {colleagues.length === 0 && team.needsReplacement === 0 ? (
                        <p className="mt-2 text-sm">
                            Por agora é a única pessoa escalada neste evento.
                        </p>
                    ) : (
                        <dl className="mt-2 space-y-2 text-sm">
                            {byRole.map(([role, names]) => (
                                <div key={role}>
                                    <dt className="text-slate-muted">{role}</dt>
                                    <dd className="font-medium">
                                        {names.join(', ')}
                                    </dd>
                                </div>
                            ))}
                            {team.needsReplacement > 0 && (
                                <div>
                                    <dt className="sr-only">Por substituir</dt>
                                    <dd className="text-tally">
                                        {team.needsReplacement === 1
                                            ? '1 lugar por substituir'
                                            : `${team.needsReplacement} lugares por substituir`}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}
                    <Link
                        href={teamSchedule(shift.scheduleId, {
                            query: { evento: shift.eventId },
                        })}
                        className="mt-3 inline-flex min-h-11 items-center text-sm underline underline-offset-4"
                    >
                        Ver a escala deste evento
                    </Link>
                </div>
            )}

            {shift.canAct && (
                <ShiftActions
                    shift={shift}
                    tone="slate"
                    className="mt-3 border-t border-slate-line pt-4"
                />
            )}
        </section>
    );
}
