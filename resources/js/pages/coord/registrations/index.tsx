import { Head, Link } from '@inertiajs/react';
import { DataTable, dataTableColumns } from '@/components/app/data-table';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import { index, show } from '@/routes/coord/registrations';
import type { VolunteerSummary } from '@/types';

const helper = dataTableColumns<VolunteerSummary>();

function Flags({ volunteer }: { volunteer: VolunteerSummary }) {
    return (
        <span className="flex flex-wrap gap-1">
            {volunteer.status === 'needs_correction' && (
                <Badge variant="outline">Em correcção</Badge>
            )}
            {volunteer.isMinor && <Badge variant="secondary">Menor</Badge>}
            {volunteer.previouslyRejectedAt && (
                <Badge variant="outline">Cadastro anterior rejeitado</Badge>
            )}
        </span>
    );
}

const columns = helper.columns([
    helper.accessor('fullName', {
        header: 'Nome',
        cell: (info) => (
            <Link
                href={show(info.row.original.id)}
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
    helper.accessor('submittedAt', {
        header: 'Enviado',
        cell: (info) =>
            info.getValue() ? formatDate(info.getValue() as string) : '—',
    }),
    helper.display({
        id: 'flags',
        header: 'Notas',
        cell: (info) => <Flags volunteer={info.row.original} />,
    }),
]);

export default function RegistrationsIndex({
    registrations,
}: {
    registrations: VolunteerSummary[];
}) {
    return (
        <>
            <Head title="Cadastros" />

            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Cadastros por rever
                    </h1>
                    <p className="text-muted-foreground">
                        {registrations.length === 0
                            ? 'Não há cadastros à espera.'
                            : `${registrations.length} à espera de revisão.`}
                    </p>
                </div>

                <DataTable
                    label="Cadastros por rever"
                    columns={columns}
                    data={registrations}
                    searchPlaceholder="Pesquisar por nome ou área…"
                    emptyMessage="Não há cadastros à espera."
                    renderCard={(row) => (
                        <Link
                            href={show(row.original.id)}
                            className="block min-h-11 space-y-1"
                        >
                            <p className="font-medium">
                                {row.original.fullName}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {row.original.roles.join(', ')}
                            </p>
                            <Flags volunteer={row.original} />
                        </Link>
                    )}
                />
            </div>
        </>
    );
}

RegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Cadastros', href: index() }],
};
