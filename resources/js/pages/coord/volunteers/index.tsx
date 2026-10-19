import { Head, Link } from '@inertiajs/react';
import { DataTable, dataTableColumns } from '@/components/app/data-table';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { edit, index } from '@/routes/coord/volunteers';
import type { VolunteerSummary } from '@/types';

const helper = dataTableColumns<VolunteerSummary>();

const columns = helper.columns([
    helper.accessor('fullName', {
        header: 'Nome',
        cell: (info) => (
            <Link
                href={edit(info.row.original.id)}
                className="font-medium underline-offset-4 hover:underline"
            >
                {info.getValue()}
            </Link>
        ),
    }),
    helper.accessor((row) => row.roles.join(', '), {
        id: 'roles',
        header: 'Áreas',
    }),
    helper.accessor('statusLabel', {
        header: 'Estado',
        cell: (info) => (
            <Badge
                variant={
                    info.row.original.status === 'active'
                        ? 'default'
                        : 'secondary'
                }
            >
                {info.getValue()}
            </Badge>
        ),
    }),
]);

const filterLinkClass = (active: boolean) =>
    cn(
        'inline-flex min-h-11 items-center rounded-md border px-3 text-sm font-medium',
        active ? 'border-primary bg-muted' : 'text-muted-foreground',
    );

export default function VolunteersIndex({
    volunteers,
    filter,
}: {
    volunteers: VolunteerSummary[];
    filter: 'sem-turnos' | null;
}) {
    return (
        <>
            <Head title="Voluntários" />

            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Voluntários</h1>
                    <p className="text-muted-foreground">
                        {filter === 'sem-turnos'
                            ? `${volunteers.length} voluntários activos sem turnos há 3 meses. É só uma sugestão: ninguém é desactivado automaticamente.`
                            : `${volunteers.length} voluntários aprovados.`}
                    </p>
                </div>

                <nav aria-label="Filtros" className="flex flex-wrap gap-2">
                    <Link
                        href={index()}
                        className={filterLinkClass(filter === null)}
                        aria-current={filter === null ? 'page' : undefined}
                    >
                        Todos
                    </Link>
                    <Link
                        href={index({ query: { filtro: 'sem-turnos' } })}
                        className={filterLinkClass(filter === 'sem-turnos')}
                        aria-current={
                            filter === 'sem-turnos' ? 'page' : undefined
                        }
                    >
                        Sem turnos há 3 meses
                    </Link>
                </nav>

                <DataTable
                    label="Voluntários"
                    columns={columns}
                    data={volunteers}
                    searchPlaceholder="Pesquisar por nome ou área…"
                    emptyMessage="Ainda não há voluntários aprovados."
                    renderCard={(row) => (
                        <Link
                            href={edit(row.original.id)}
                            className="block min-h-11 space-y-1"
                        >
                            <p className="flex items-center justify-between gap-2 font-medium">
                                {row.original.fullName}
                                <Badge variant="secondary">
                                    {row.original.statusLabel}
                                </Badge>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {row.original.roles.join(', ')}
                            </p>
                        </Link>
                    )}
                />
            </div>
        </>
    );
}

VolunteersIndex.layout = {
    breadcrumbs: [{ title: 'Voluntários', href: index() }],
};
