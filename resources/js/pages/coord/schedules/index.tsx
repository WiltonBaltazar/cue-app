import { Head, Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { CheckboxField, TextField } from '@/components/volunteer-form/fields';
import { formatDate } from '@/lib/format';
import {
    destroy,
    destroyMany,
    edit,
    index,
    store,
} from '@/routes/coord/schedules';

type ScheduleRow = {
    id: number;
    startsOn: string;
    endsOn: string;
    status: 'draft' | 'published';
    statusLabel: string;
    assignments: number;
    /** Only non-zero for published schedules: who is told, and history that goes. */
    deleteImpact: { shifts: number; people: number; past: number };
};

function period(schedule: ScheduleRow): string {
    return `${formatDate(`${schedule.startsOn}T12:00:00Z`)} – ${formatDate(`${schedule.endsOn}T12:00:00Z`)}`;
}

/**
 * What deleting means, said plainly before it happens (FR-039).
 */
function deleteDescription(rows: ScheduleRow[]): string {
    const people = rows.reduce((sum, row) => sum + row.deleteImpact.people, 0);
    const past = rows.reduce((sum, row) => sum + row.deleteImpact.past, 0);
    const assignments = rows.reduce((sum, row) => sum + row.assignments, 0);

    const parts = [
        assignments === 1
            ? 'A atribuição é apagada.'
            : `As ${assignments} atribuições são apagadas.`,
        people > 0
            ? `${people} voluntários com turnos futuros serão avisados.`
            : 'Ninguém é notificado.',
    ];

    if (past > 0) {
        parts.push(
            `Inclui ${past} turnos já realizados: saem também do histórico de participação.`,
        );
    }

    return `${parts.join(' ')} Não é possível desfazer.`;
}

type Props = {
    schedules: ScheduleRow[];
    suggestedStart: string;
    suggestedEnd: string;
};

export default function SchedulesIndex({
    schedules,
    suggestedStart,
    suggestedEnd,
}: Props) {
    const form = useForm({
        starts_on: suggestedStart,
        ends_on: suggestedEnd,
        generate: true,
    });
    const [selected, setSelected] = useState<number[]>([]);
    const [deleting, setDeleting] = useState(false);
    const selectedRows = schedules.filter((schedule) =>
        selected.includes(schedule.id),
    );

    const toggle = (id: number, on: boolean) =>
        setSelected((current) =>
            on
                ? [...current, id]
                : current.filter((selectedId) => selectedId !== id),
        );

    const deleteOptions = {
        preserveScroll: true,
        onStart: () => setDeleting(true),
        onFinish: () => setDeleting(false),
        onSuccess: () => setSelected([]),
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url);
    };

    return (
        <>
            <Head title="Escalas" />

            <div className="space-y-6 p-4 lg:max-w-3xl">
                <h1 className="text-2xl font-semibold">Escalas</h1>

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-lg border p-4"
                >
                    <Heading
                        variant="small"
                        title="Nova escala"
                        description="O sistema pode propor as atribuições respeitando funções, disponibilidade e ausências, ou pode começar vazia e preencher à mão. Depois ajusta e publica."
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            label="De"
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                            error={form.errors.starts_on}
                        />
                        <TextField
                            label="Até"
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                            error={form.errors.ends_on}
                        />
                    </div>
                    <CheckboxField
                        label="Gerar proposta automaticamente"
                        hint="Desmarque para criar a escala vazia e escolher cada pessoa à mão."
                        checked={form.data.generate}
                        onChange={(checked) =>
                            form.setData('generate', checked)
                        }
                    />
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        {form.data.generate
                            ? 'Gerar proposta'
                            : 'Criar escala vazia'}
                    </Button>
                </form>

                {schedules.length > 0 && (
                    <div className="flex min-h-11 flex-wrap items-center gap-3">
                        <label
                            htmlFor="select-all-schedules"
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                id="select-all-schedules"
                                className="size-5"
                                checked={
                                    selected.length === schedules.length
                                        ? true
                                        : selected.length > 0
                                          ? 'indeterminate'
                                          : false
                                }
                                onCheckedChange={(checked) =>
                                    setSelected(
                                        checked === true
                                            ? schedules.map(
                                                  (schedule) => schedule.id,
                                              )
                                            : [],
                                    )
                                }
                            />
                            Seleccionar todas
                        </label>
                        {selected.length > 0 && (
                            <ConfirmDialog
                                title={
                                    selected.length === 1
                                        ? 'Apagar 1 escala?'
                                        : `Apagar ${selected.length} escalas?`
                                }
                                description={deleteDescription(selectedRows)}
                                confirmLabel="Apagar"
                                destructive
                                processing={deleting}
                                onConfirm={() =>
                                    router.delete(destroyMany().url, {
                                        data: { ids: selected },
                                        ...deleteOptions,
                                    })
                                }
                            >
                                <Button
                                    variant="destructive"
                                    className="ml-auto min-h-11"
                                    disabled={deleting}
                                >
                                    <Trash2 aria-hidden="true" />
                                    Apagar {selected.length}{' '}
                                    {selected.length === 1
                                        ? 'seleccionada'
                                        : 'seleccionadas'}
                                </Button>
                            </ConfirmDialog>
                        )}
                    </div>
                )}

                <ul className="divide-y rounded-lg border">
                    {schedules.length === 0 && (
                        <li className="p-4 text-center text-sm text-muted-foreground">
                            Ainda não há escalas.
                        </li>
                    )}
                    {schedules.map((schedule) => (
                        <li
                            key={schedule.id}
                            className="flex items-center gap-2 pl-4"
                        >
                            <Checkbox
                                className="size-5"
                                checked={selected.includes(schedule.id)}
                                onCheckedChange={(checked) =>
                                    toggle(schedule.id, checked === true)
                                }
                                aria-label={`Seleccionar escala ${period(schedule)}`}
                            />
                            <Link
                                href={edit(schedule.id)}
                                className="flex min-h-11 flex-1 items-center gap-3 p-4 hover:bg-muted/50"
                            >
                                <span className="flex-1">
                                    <span className="block font-medium">
                                        {period(schedule)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {schedule.assignments} atribuições
                                    </span>
                                </span>
                                <Badge
                                    variant={
                                        schedule.status === 'published'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {schedule.statusLabel}
                                </Badge>
                            </Link>
                            <ConfirmDialog
                                title={`Apagar a escala ${period(schedule)}?`}
                                description={deleteDescription([schedule])}
                                confirmLabel="Apagar"
                                destructive
                                processing={deleting}
                                onConfirm={() =>
                                    router.delete(
                                        destroy(schedule.id).url,
                                        deleteOptions,
                                    )
                                }
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 size-11"
                                    aria-label={`Apagar escala ${period(schedule)}`}
                                    disabled={deleting}
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </ConfirmDialog>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

SchedulesIndex.layout = {
    breadcrumbs: [{ title: 'Escalas', href: index() }],
};
