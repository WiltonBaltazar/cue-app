import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateBlock } from '@/components/app/date-block';
import { Button } from '@/components/ui/button';
import { formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { accept, cancel } from '@/routes/swaps';
import { index as myShifts } from '@/routes/volunteer/shifts';

type SwapBase = {
    id: number;
    status: string;
    statusLabel: string;
    isUrgent: boolean;
    shift: string;
    eventName: string;
    role: string;
    date: string;
    arrivalTime: string;
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

/** Date, event, role and arrival time: the same reading order as "A minha escala". */
function SwapRow({
    swap,
    children,
    footer,
}: {
    swap: SwapBase;
    children: ReactNode;
    footer?: ReactNode;
}) {
    return (
        <li className="px-4 py-3">
            <div className="flex items-center gap-3">
                <DateBlock date={swap.date} />
                <div className="min-w-0 flex-1">
                    <p className="sr-only">
                        {formatLongDate(`${swap.date}T12:00:00Z`)}:
                    </p>
                    <p className="truncate font-medium">{swap.eventName}</p>
                    <p className="truncate text-sm text-muted-foreground">
                        Função: {swap.role}
                    </p>
                    {children}
                </div>
                <p className="text-right leading-none">
                    <span className="sr-only">Chegada às </span>
                    <span className="font-display text-2xl font-semibold tabular-nums">
                        {swap.arrivalTime}
                    </span>
                    <span
                        className="mt-1 block text-xs text-muted-foreground"
                        aria-hidden="true"
                    >
                        chegada
                    </span>
                </p>
            </div>
            {footer}
        </li>
    );
}

/** Text colour of a request's state, so "approved" and "open" read at a glance. */
const statusTone: Record<string, string> = {
    open: 'text-amber-700 dark:text-amber-400',
    accepted: 'text-primary',
    approved: 'text-emerald-700 dark:text-emerald-400',
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

            <h1 className="font-display text-3xl font-semibold">Trocas</h1>

            <section aria-labelledby="open-to-me" className="mt-6">
                <h2
                    id="open-to-me"
                    className="font-display text-xl font-semibold"
                >
                    Colegas que precisam de substituto
                </h2>
                {openToMe.length === 0 ? (
                    <p className="mt-2 text-muted-foreground">
                        Ninguém precisa de substituto num turno que possa fazer.
                        Quando alguém precisar, recebe um aviso.
                    </p>
                ) : (
                    <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {openToMe.map((swap) => (
                            <SwapRow
                                key={swap.id}
                                swap={swap}
                                footer={
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
                                        <Button className="mt-3 min-h-11 w-full sm:w-auto">
                                            Posso substituir
                                        </Button>
                                    </ConfirmDialog>
                                }
                            >
                                <p className="mt-1 text-sm">
                                    {swap.directedToMe
                                        ? `Pedido a si por ${swap.requester}`
                                        : `Pedido por ${swap.requester}`}
                                </p>
                                {swap.isUrgent && (
                                    <p className="text-sm font-medium text-destructive-foreground">
                                        Urgente: falta pouco para o evento
                                    </p>
                                )}
                            </SwapRow>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="my-swaps" className="mt-8">
                <h2
                    id="my-swaps"
                    className="font-display text-xl font-semibold"
                >
                    Os meus pedidos
                </h2>
                {mine.length === 0 ? (
                    <p className="mt-2 text-muted-foreground">
                        Para pedir uma troca, abra o turno em{' '}
                        <Link
                            href={myShifts()}
                            className="font-medium text-primary underline underline-offset-4"
                        >
                            A minha escala
                        </Link>{' '}
                        e toque em "Pedir troca".
                    </p>
                ) : (
                    <ul className="mt-3 divide-y overflow-hidden rounded-2xl border bg-card">
                        {mine.map((swap) => (
                            <SwapRow
                                key={swap.id}
                                swap={swap}
                                footer={
                                    swap.canCancel && (
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
                                    )
                                }
                            >
                                <p
                                    className={cn(
                                        'mt-1 text-sm font-medium',
                                        statusTone[swap.status] ??
                                            'text-muted-foreground',
                                    )}
                                >
                                    {swap.statusLabel}
                                    {swap.isUrgent &&
                                        swap.status === 'open' &&
                                        ', urgente'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {swap.acceptedBy
                                        ? `Aceite por ${swap.acceptedBy}`
                                        : swap.target
                                          ? `Pedido a ${swap.target}`
                                          : 'Pedido a todos os colegas disponíveis'}
                                </p>
                            </SwapRow>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
