import { Head, Link, useForm } from '@inertiajs/react';
import { Pencil, UserPlus } from 'lucide-react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/volunteer-form/fields';
import { formatLongDate, formatShortDate } from '@/lib/format';
import {
    cancel,
    edit,
    index,
    schedule as eventSchedule,
} from '@/routes/coord/events';
import { edit as editSchedule } from '@/routes/coord/schedules';
import type { EventFull } from '@/types';

type Props = {
    event: EventFull;
    affectedVolunteers: number;
    schedule: {
        id: number;
        singleEvent: boolean;
        startsOn: string;
        endsOn: string;
    } | null;
};

export default function EventShow({
    event,
    affectedVolunteers,
    schedule,
}: Props) {
    const cancellation = useForm({ reason: '' });
    const staffing = useForm({});
    const createOwnSchedule = () => staffing.post(eventSchedule(event.id).url);
    const cancelled = event.status === 'cancelled';
    const errors = cancellation.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={event.name} />

            <div className="space-y-6 p-4 lg:max-w-3xl">
                <header className="space-y-2">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <h1
                            className={`text-2xl font-semibold ${cancelled ? 'line-through' : ''}`}
                        >
                            {event.name}
                        </h1>
                        {!cancelled && (
                            <Button
                                variant="outline"
                                asChild
                                className="min-h-11"
                            >
                                <Link href={edit(event.id)}>
                                    <Pencil aria-hidden="true" />
                                    Editar
                                </Link>
                            </Button>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant={cancelled ? 'secondary' : 'default'}>
                            {event.statusLabel}
                        </Badge>
                        <Badge variant="outline">{event.typeLabel}</Badge>
                        {event.isCustomized && (
                            <Badge variant="outline">
                                Alterado em relação ao modelo
                            </Badge>
                        )}
                    </div>
                    {cancelled && event.cancelReason && (
                        <p className="text-sm text-muted-foreground">
                            Motivo: {event.cancelReason}
                        </p>
                    )}
                </header>

                <dl className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted-foreground">Data</dt>
                        <dd className="first-letter:uppercase">
                            {formatLongDate(`${event.date}T12:00:00Z`)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">Local</dt>
                        <dd>{event.location}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Chegada da equipa
                        </dt>
                        <dd>{event.arrivalTime}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Evento
                        </dt>
                        <dd>
                            {event.startTime}–{event.endTime}
                            {event.teardownMinutes > 0 &&
                                ` (+${event.teardownMinutes} min de desmontagem)`}
                        </dd>
                    </div>
                </dl>

                <section className="space-y-2">
                    <Heading
                        variant="small"
                        title={`Posições (${event.requiredPeople} pessoas)`}
                    />
                    <ul className="divide-y rounded-lg border">
                        {event.positions.map((position) => (
                            <li
                                key={position.teamRoleId}
                                className="flex items-center justify-between gap-2 p-3"
                            >
                                <span>
                                    {position.quantity}× {position.name}
                                </span>
                                {position.minLevelLabel && (
                                    <span className="text-sm text-muted-foreground">
                                        nível mínimo{' '}
                                        {position.minLevelLabel.toLowerCase()}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                {!cancelled && (
                    <section className="space-y-3 rounded-lg border p-4">
                        <Heading
                            variant="small"
                            title="Voluntários"
                            description={
                                schedule === null
                                    ? 'Este evento ainda não está em nenhuma escala. Crie a escala dele para escolher as pessoas.'
                                    : schedule.singleEvent
                                      ? 'Este evento tem uma escala só para ele.'
                                      : `Este evento está na escala de ${formatShortDate(`${schedule.startsOn}T12:00:00Z`)} a ${formatShortDate(`${schedule.endsOn}T12:00:00Z`)}.`
                            }
                        />
                        <div className="flex flex-wrap gap-2">
                            {schedule === null ? (
                                <Button
                                    className="min-h-11"
                                    disabled={staffing.processing}
                                    onClick={createOwnSchedule}
                                >
                                    <UserPlus aria-hidden="true" />
                                    Escolher voluntários
                                </Button>
                            ) : (
                                <Button asChild className="min-h-11">
                                    <Link href={editSchedule(schedule.id)}>
                                        <UserPlus aria-hidden="true" />
                                        Escolher voluntários
                                    </Link>
                                </Button>
                            )}
                            {schedule !== null && !schedule.singleEvent && (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    disabled={staffing.processing}
                                    onClick={createOwnSchedule}
                                >
                                    Criar escala só para este evento
                                </Button>
                            )}
                        </div>
                        <InputError
                            message={
                                (
                                    staffing.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).event
                            }
                        />
                    </section>
                )}

                {!cancelled && (
                    <section className="space-y-3 rounded-lg border p-4">
                        <Heading variant="small" title="Cancelar evento" />
                        <TextField
                            label="Motivo"
                            optional
                            value={cancellation.data.reason}
                            onChange={(changeEvent) =>
                                cancellation.setData(
                                    'reason',
                                    changeEvent.target.value,
                                )
                            }
                            error={errors.reason}
                        />
                        <InputError message={errors.event} />
                        <ConfirmDialog
                            title="Cancelar este evento?"
                            description={
                                affectedVolunteers > 0
                                    ? `${affectedVolunteers} voluntários escalados serão notificados.`
                                    : 'Ainda não há voluntários escalados para este evento.'
                            }
                            confirmLabel="Cancelar evento"
                            cancelLabel="Voltar"
                            destructive
                            processing={cancellation.processing}
                            onConfirm={() =>
                                cancellation.post(cancel(event.id).url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Button variant="destructive" className="min-h-11">
                                Cancelar evento
                            </Button>
                        </ConfirmDialog>
                    </section>
                )}
            </div>
        </>
    );
}

EventShow.layout = {
    breadcrumbs: [{ title: 'Eventos', href: index() }],
};
