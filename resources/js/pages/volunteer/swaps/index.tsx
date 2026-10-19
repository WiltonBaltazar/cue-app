import { Head, router } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { accept, cancel } from '@/routes/swaps';

type SwapBase = {
    id: number;
    status: string;
    statusLabel: string;
    isUrgent: boolean;
    shift: string;
    date: string;
    startTime: string;
};

type MySwap = SwapBase & {
    target: string | null;
    acceptedBy: string | null;
    canCancel: boolean;
};

type OpenSwap = SwapBase & {
    requester: string;
    directedToMe: boolean;
};

/**
 * Swaps (US4): requests I can take, then my own requests. Only shifts I am eligible
 * for are offered to me (US4-2).
 */
export default function Swaps({
    mine,
    openToMe,
}: {
    mine: MySwap[];
    openToMe: OpenSwap[];
}) {
    return (
        <>
            <Head title="Trocas" />

            <h1 className="text-2xl font-semibold">Trocas</h1>

            <section aria-labelledby="open-to-me" className="mt-6 space-y-3">
                <h2 id="open-to-me" className="font-medium">
                    Pedidos que pode aceitar
                </h2>
                {openToMe.length === 0 ? (
                    <div className="rounded-xl border p-6 text-center">
                        <ArrowLeftRight
                            className="mx-auto size-10 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 text-sm text-muted-foreground">
                            Nenhum colega precisa de substituto num turno que
                            possa fazer.
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {openToMe.map((swap) => (
                            <li key={swap.id} className="rounded-xl border p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    {swap.isUrgent && (
                                        <Badge variant="destructive">
                                            Urgente
                                        </Badge>
                                    )}
                                    {swap.directedToMe && (
                                        <Badge variant="secondary">
                                            Pedido a si
                                        </Badge>
                                    )}
                                </div>
                                <p className="mt-2 font-medium">{swap.shift}</p>
                                <p className="text-sm text-muted-foreground">
                                    Pedido por {swap.requester}
                                </p>
                                <ConfirmDialog
                                    title="Assumir este turno?"
                                    description={`${swap.shift}. Pode ainda precisar da aprovação de um coordenador.`}
                                    confirmLabel="Assumir turno"
                                    onConfirm={() =>
                                        router.post(
                                            accept(swap.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Button className="mt-3 min-h-11">
                                        Posso substituir
                                    </Button>
                                </ConfirmDialog>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="my-swaps" className="mt-8 space-y-3">
                <h2 id="my-swaps" className="font-medium">
                    Os meus pedidos
                </h2>
                {mine.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Para pedir uma troca, abra o turno em "Escala" e toque
                        em "Pedir troca".
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {mine.map((swap) => (
                            <li key={swap.id} className="rounded-xl border p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            swap.status === 'approved'
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {swap.statusLabel}
                                    </Badge>
                                    {swap.isUrgent &&
                                        swap.status === 'open' && (
                                            <Badge variant="destructive">
                                                Urgente
                                            </Badge>
                                        )}
                                </div>
                                <p className="mt-2 font-medium">{swap.shift}</p>
                                <p className="text-sm text-muted-foreground">
                                    {swap.acceptedBy
                                        ? `Aceite por ${swap.acceptedBy}`
                                        : swap.target
                                          ? `Pedido a ${swap.target}`
                                          : 'Pedido a todos os colegas disponíveis'}
                                </p>
                                {swap.canCancel && (
                                    <ConfirmDialog
                                        title="Cancelar o pedido?"
                                        description="O turno continua seu."
                                        confirmLabel="Cancelar pedido"
                                        cancelLabel="Voltar"
                                        destructive
                                        onConfirm={() =>
                                            router.post(
                                                cancel(swap.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Button
                                            variant="ghost"
                                            className="mt-2 min-h-11"
                                        >
                                            Cancelar pedido
                                        </Button>
                                    </ConfirmDialog>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
