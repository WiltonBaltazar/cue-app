import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes/coord';

/**
 * Coordinator dashboard. Pending approvals, urgent swaps and coverage gaps
 * arrive with later user stories (T111).
 */
export default function CoordinatorDashboard() {
    return (
        <>
            <Head title="Painel" />

            <div className="p-4">
                <h1 className="text-2xl font-semibold">Painel</h1>
                <p className="mt-2 text-muted-foreground">
                    Aqui vai ver os cadastros pendentes, as trocas urgentes e as
                    lacunas nas próximas escalas.
                </p>
            </div>
        </>
    );
}

CoordinatorDashboard.layout = {
    breadcrumbs: [{ title: 'Painel', href: dashboard() }],
};
