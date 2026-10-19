import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PositionsEditor } from '@/components/app/positions-editor';
import type { PositionInput } from '@/components/app/positions-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import type { Option } from '@/components/volunteer-form/types';
import { index, store, update } from '@/routes/coord/events';
import type { EventFull } from '@/types';

type Props = {
    event: EventFull | null;
    defaultDate: string;
    teamRoles: { id: number; name: string }[];
    levels: Option[];
};

/**
 * Special event, or one occurrence of a recurring activity (FR-008, FR-009).
 */
export default function EventForm({
    event,
    defaultDate,
    teamRoles,
    levels,
}: Props) {
    const form = useForm({
        name: event?.name ?? '',
        date: event?.date ?? defaultDate,
        arrival_time: event?.arrivalTime ?? '',
        start_time: event?.startTime ?? '',
        end_time: event?.endTime ?? '',
        teardown_minutes: event?.teardownMinutes ?? 30,
        location: event?.location ?? '',
        positions: (event?.positions.map((position) => ({
            team_role_id: position.teamRoleId,
            quantity: position.quantity,
            min_level: position.minLevel,
        })) ?? []) as PositionInput[],
    });

    const errors = form.errors as Record<string, string | undefined>;
    const isOccurrence = event?.templateId != null;

    const submit = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();

        if (event) {
            form.put(update(event.id).url);
        } else {
            form.post(store().url);
        }
    };

    const title = event
        ? `Editar: ${event.name}`
        : 'Novo evento extraordinário';

    return (
        <>
            <Head title={title} />

            <form onSubmit={submit} className="space-y-6 p-4 lg:max-w-3xl">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {isOccurrence && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            As alterações aplicam-se só a esta data. As
                            restantes ocorrências mantêm o modelo.
                        </p>
                    )}
                </div>

                <InputError message={errors.event} />

                <TextField
                    label="Nome"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={errors.name}
                />
                <TextField
                    label="Data"
                    type="date"
                    value={form.data.date}
                    onChange={(e) => form.setData('date', e.target.value)}
                    error={errors.date}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <TextField
                        label="Chegada da equipa"
                        type="time"
                        value={form.data.arrival_time}
                        onChange={(e) =>
                            form.setData('arrival_time', e.target.value)
                        }
                        hint="Montagem antes do início."
                        error={errors.arrival_time}
                    />
                    <TextField
                        label="Início"
                        type="time"
                        value={form.data.start_time}
                        onChange={(e) =>
                            form.setData('start_time', e.target.value)
                        }
                        error={errors.start_time}
                    />
                    <TextField
                        label="Fim"
                        type="time"
                        value={form.data.end_time}
                        onChange={(e) =>
                            form.setData('end_time', e.target.value)
                        }
                        error={errors.end_time}
                    />
                </div>

                <TextField
                    label="Desmontagem (minutos)"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={480}
                    value={form.data.teardown_minutes}
                    onChange={(e) =>
                        form.setData('teardown_minutes', Number(e.target.value))
                    }
                    hint="Tempo depois do fim em que a equipa ainda está ocupada."
                    error={errors.teardown_minutes}
                />
                <TextField
                    label="Local"
                    value={form.data.location}
                    onChange={(e) => form.setData('location', e.target.value)}
                    error={errors.location}
                />

                <PositionsEditor
                    roles={teamRoles}
                    levels={levels}
                    value={form.data.positions}
                    onChange={(positions) =>
                        form.setData('positions', positions)
                    }
                    errors={errors}
                />

                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {event ? 'Guardar alterações' : 'Criar evento'}
                </Button>
            </form>
        </>
    );
}

EventForm.layout = {
    breadcrumbs: [{ title: 'Eventos', href: index() }],
};
