import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { PositionsEditor } from '@/components/app/positions-editor';
import type { PositionInput } from '@/components/app/positions-editor';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    CheckboxField,
    RadioGroupField,
    TextField,
} from '@/components/volunteer-form/fields';
import type { Option } from '@/components/volunteer-form/types';
import { index, store, update } from '@/routes/coord/templates';

type TemplateData = {
    id: number;
    name: string;
    weekday: number;
    intervalWeeks: number;
    startsOn: string;
    arrivalTime: string;
    startTime: string;
    endTime: string;
    teardownMinutes: number;
    location: string;
    isActive: boolean;
    positions: {
        teamRoleId: number;
        quantity: number;
        minLevel: string | null;
    }[];
};

type Props = {
    template: TemplateData | null;
    teamRoles: { id: number; name: string }[];
    levels: Option[];
    weekdays: Option<number>[];
    today: string;
};

const INTERVALS: Option[] = [
    { value: '1', label: 'Todas as semanas' },
    { value: '2', label: 'De 2 em 2 semanas' },
    { value: '3', label: 'De 3 em 3 semanas' },
    { value: '4', label: 'De 4 em 4 semanas' },
];

/**
 * Recurring activity (FR-008). Saving updates future dates that were not changed by hand.
 */
export default function TemplateForm({
    template,
    teamRoles,
    levels,
    weekdays,
    today,
}: Props) {
    const form = useForm({
        name: template?.name ?? '',
        weekday: template?.weekday ?? 7,
        interval_weeks: template?.intervalWeeks ?? 1,
        starts_on: template?.startsOn ?? today,
        arrival_time: template?.arrivalTime ?? '',
        start_time: template?.startTime ?? '',
        end_time: template?.endTime ?? '',
        teardown_minutes: template?.teardownMinutes ?? 30,
        location: template?.location ?? '',
        is_active: template?.isActive ?? true,
        positions: (template?.positions.map((position) => ({
            team_role_id: position.teamRoleId,
            quantity: position.quantity,
            min_level: position.minLevel,
        })) ?? []) as PositionInput[],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const save = () => {
        if (template) {
            form.put(update(template.id).url);
        } else {
            form.post(store().url);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        save();
    };

    const title = template ? template.name : 'Nova actividade recorrente';

    return (
        <>
            <Head title={title} />

            <form onSubmit={submit} className="space-y-6 p-4 lg:max-w-3xl">
                <h1 className="text-2xl font-semibold">{title}</h1>

                <TextField
                    label="Nome"
                    placeholder="Ex.: Culto de Domingo"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={errors.name}
                />

                <RadioGroupField
                    legend="Dia da semana"
                    name="weekday"
                    options={weekdays.map((day) => ({
                        value: String(day.value),
                        label: day.label,
                    }))}
                    value={String(form.data.weekday)}
                    onChange={(day) => form.setData('weekday', Number(day))}
                    error={errors.weekday}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <RadioGroupField
                        legend="Repetição"
                        name="interval_weeks"
                        options={INTERVALS}
                        value={String(form.data.interval_weeks)}
                        onChange={(interval) =>
                            form.setData('interval_weeks', Number(interval))
                        }
                        error={errors.interval_weeks}
                    />
                    <TextField
                        label="A partir de"
                        type="date"
                        value={form.data.starts_on}
                        onChange={(e) =>
                            form.setData('starts_on', e.target.value)
                        }
                        hint="Primeira semana da repetição."
                        error={errors.starts_on}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <TextField
                        label="Chegada da equipa"
                        type="time"
                        value={form.data.arrival_time}
                        onChange={(e) =>
                            form.setData('arrival_time', e.target.value)
                        }
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

                <CheckboxField
                    label="Activa (gera datas no calendário)"
                    hint="Ao desactivar, as datas futuras não alteradas à mão são removidas."
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                />

                {template ? (
                    <ConfirmDialog
                        title="Guardar alterações?"
                        description="As datas futuras desta actividade que não foram alteradas à mão passam a seguir o modelo."
                        confirmLabel="Guardar"
                        processing={form.processing}
                        onConfirm={save}
                    >
                        <Button
                            type="button"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            Guardar
                        </Button>
                    </ConfirmDialog>
                ) : (
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Criar actividade
                    </Button>
                )}
            </form>
        </>
    );
}

TemplateForm.layout = {
    breadcrumbs: [{ title: 'Actividades recorrentes', href: index() }],
};
