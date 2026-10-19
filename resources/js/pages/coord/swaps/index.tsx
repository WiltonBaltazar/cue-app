import { Head, Link, router } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editSchedule } from '@/routes/coord/schedules';
import { decide, index } from '@/routes/coord/swaps';

type Swap = {
    id: number;
    status: string;
    statusLabel: string;
    isUrgent: boolean;
    shift: string;
    requester: string;
    target: string | null;
    acceptedBy: string | null;
    scheduleId: number;
};

type OpenShift = {
    id: number;
    shift: string;
    volunteer: string;
    scheduleId: number;
};

/**
 * Swaps waiting for approval and shifts that need a substitute (US4). Substitutes are
 * picked in the schedule editor, which checks the rules.
 */
export default function CoordinatorSwaps({
    swaps,
    needsReplacement,
}: {
    swaps: Swap[];
    needsReplacement: OpenShift[];
}) {
    const send = (swap: Swap, decision: 'approve' | 'reject') =>
        router.post(
            decide(swap.id).url,
            { decision },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Trocas" />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <h1 className="text-2xl font-semibold">Trocas</h1>

                <section
                    aria-labelledby="needs-replacement"
                    className="space-y-3"
                >
                    <h2 id="needs-replacement" className="font-medium">
                        Turnos por substituir ({needsReplacement.length})
                    </h2>
                    {needsReplacement.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todos os turnos publicados têm alguém.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {needsReplacement.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.shift}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.volunteer} não pode ir
                                        </p>
                                    </div>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <Link
                                            href={editSchedule(item.scheduleId)}
                                        >
                                            Escolher substituto
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="swap-requests" className="space-y-3">
                    <h2 id="swap-requests" className="font-medium">
                        Pedidos de troca ({swaps.length})
                    </h2>
                    {swaps.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Não há pedidos de troca activos.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {swaps.map((swap) => (
                                <li
                                    key={swap.id}
                                    className="space-y-2 rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                swap.status === 'accepted'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {swap.status === 'accepted'
                                                ? 'À espera de aprovação'
                                                : swap.statusLabel}
                                        </Badge>
                                        {swap.isUrgent && (
                                            <Badge variant="destructive">
                                                Urgente
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="font-medium">{swap.shift}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {swap.requester}
                                        {swap.acceptedBy
                                            ? ` → ${swap.acceptedBy}`
                                            : swap.target
                                              ? ` pediu a ${swap.target}`
                                              : ' pediu a todos os colegas disponíveis'}
                                    </p>
                                    {swap.status === 'accepted' && (
                                        <div className="flex flex-wrap gap-2">
                                            <ConfirmDialog
                                                title="Aprovar a troca?"
                                                description={`${swap.acceptedBy} passa a fazer este turno. ${swap.requester} e ${swap.acceptedBy} são notificados.`}
                                                confirmLabel="Aprovar"
                                                onConfirm={() =>
                                                    send(swap, 'approve')
                                                }
                                            >
                                                <Button className="min-h-11">
                                                    Aprovar
                                                </Button>
                                            </ConfirmDialog>
                                            <ConfirmDialog
                                                title="Rejeitar a troca?"
                                                description={`O turno continua com ${swap.requester}. Ambos são notificados.`}
                                                confirmLabel="Rejeitar"
                                                destructive
                                                onConfirm={() =>
                                                    send(swap, 'reject')
                                                }
                                            >
                                                <Button
                                                    variant="outline"
                                                    className="min-h-11"
                                                >
                                                    Rejeitar
                                                </Button>
                                            </ConfirmDialog>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

CoordinatorSwaps.layout = {
    breadcrumbs: [{ title: 'Trocas', href: index() }],
};
