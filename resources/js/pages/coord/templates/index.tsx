import { Head, Link } from '@inertiajs/react';
import { Plus, Repeat } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/coord/templates';

type TemplateRow = {
    id: number;
    name: string;
    recurrenceLabel: string;
    startTime: string;
    location: string;
    isActive: boolean;
    requiredPeople: number;
};

export default function TemplatesIndex({
    templates,
}: {
    templates: TemplateRow[];
}) {
    return (
        <>
            <Head title="Actividades recorrentes" />

            <div className="space-y-4 p-4 lg:max-w-3xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Actividades recorrentes
                        </h1>
                        <p className="text-muted-foreground">
                            Geram automaticamente as datas no calendário.
                        </p>
                    </div>
                    <Button asChild className="min-h-11">
                        <Link href={create()}>
                            <Plus aria-hidden="true" />
                            Nova actividade
                        </Link>
                    </Button>
                </div>

                <ul className="divide-y rounded-lg border">
                    {templates.length === 0 && (
                        <li className="p-4 text-center text-sm text-muted-foreground">
                            Ainda não há actividades recorrentes.
                        </li>
                    )}
                    {templates.map((template) => (
                        <li key={template.id}>
                            <Link
                                href={edit(template.id)}
                                className="flex min-h-11 items-center gap-3 p-4 hover:bg-muted/50"
                            >
                                <Repeat
                                    className="size-5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium">
                                        {template.name}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {template.recurrenceLabel} às{' '}
                                        {template.startTime} ·{' '}
                                        {template.location} ·{' '}
                                        {template.requiredPeople} pessoas
                                    </span>
                                </span>
                                {!template.isActive && (
                                    <Badge variant="secondary">Inactiva</Badge>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

TemplatesIndex.layout = {
    breadcrumbs: [{ title: 'Actividades recorrentes', href: index() }],
};
