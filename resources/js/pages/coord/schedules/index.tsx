import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import { formatDate } from '@/lib/format';
import { edit, index, store } from '@/routes/coord/schedules';

type ScheduleRow = {
    id: number;
    startsOn: string;
    endsOn: string;
    status: 'draft' | 'published';
    statusLabel: string;
    assignments: number;
};

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
    const form = useForm({ starts_on: suggestedStart, ends_on: suggestedEnd });

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
                        description="O sistema propõe as atribuições respeitando disponibilidade, ausências, níveis e frequência. Depois pode ajustar e publicar."
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
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Gerar proposta
                    </Button>
                </form>

                <ul className="divide-y rounded-lg border">
                    {schedules.length === 0 && (
                        <li className="p-4 text-center text-sm text-muted-foreground">
                            Ainda não há escalas.
                        </li>
                    )}
                    {schedules.map((schedule) => (
                        <li key={schedule.id}>
                            <Link
                                href={edit(schedule.id)}
                                className="flex min-h-11 items-center gap-3 p-4 hover:bg-muted/50"
                            >
                                <span className="flex-1">
                                    <span className="block font-medium">
                                        {formatDate(
                                            `${schedule.startsOn}T12:00:00Z`,
                                        )}{' '}
                                        –{' '}
                                        {formatDate(
                                            `${schedule.endsOn}T12:00:00Z`,
                                        )}
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
