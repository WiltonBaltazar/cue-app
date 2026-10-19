import { Head, Link } from '@inertiajs/react';
import { DataTable, dataTableColumns } from '@/components/app/data-table';
import { Badge } from '@/components/ui/badge';
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

export default function VolunteersIndex({
    volunteers,
}: {
    volunteers: VolunteerSummary[];
}) {
    return (
        <>
            <Head title="Voluntários" />

            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Voluntários</h1>
                    <p className="text-muted-foreground">
                        {volunteers.length} voluntários aprovados.
                    </p>
                </div>

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
