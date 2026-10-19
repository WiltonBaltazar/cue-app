import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CoverageItem } from '@/components/app/coverage-item';
import type { CoverageRow } from '@/components/app/coverage-item';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import { coverage, dashboard } from '@/routes/coord';
import {
    index as registrations,
    show as showRegistration,
} from '@/routes/coord/registrations';
import { index as swapsIndex } from '@/routes/coord/swaps';

type Pending = {
    id: number;
    name: string;
    status: string;
    submittedAt: string | null;
};

type DashboardSwap = {
    id: number;
    shift: string;
    isUrgent: boolean;
    awaitingDecision: boolean;
};

/**
 * What needs the coordinator now: registrations to review, swaps to decide or urgent,
 * and empty places in the next 30 days (US5).
 */
export default function CoordinatorDashboard({
    pending,
    swaps,
    swapCount,
    needsReplacement,
    gaps,
    gapDays,
}: {
    pending: { count: number; items: Pending[] };
    swaps: DashboardSwap[];
    swapCount: number;
    needsReplacement: number;
    gaps: CoverageRow[];
    gapDays: number;
}) {
    return (
        <>
            <Head title="Painel" />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <h1 className="text-2xl font-semibold">Painel</h1>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Tile
                        href={registrations()}
                        label="Cadastros por rever"
                        value={pending.count}
                    />
                    <Tile
                        href={swapsIndex()}
                        label="Trocas a decidir ou urgentes"
                        value={swapCount + needsReplacement}
                    />
                    <Tile
                        href={coverage()}
                        label={`Eventos com lugares vazios (${gapDays} dias)`}
                        value={gaps.length}
                    />
                </div>

                <Section
                    id="pending"
                    title="Cadastros pendentes"
                    href={registrations()}
                    more={pending.count > pending.items.length}
                    empty={pending.count === 0 && 'Não há cadastros por rever.'}
                >
                    <ul className="divide-y rounded-lg border">
                        {pending.items.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={showRegistration(item.id)}
                                    className="flex min-h-11 flex-wrap items-center justify-between gap-2 p-3"
                                >
                                    <span className="font-medium">
                                        {item.name}
                                    </span>
                                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                        {item.submittedAt &&
                                            formatDate(item.submittedAt)}
                                        <Badge variant="outline">
                                            {item.status}
                                        </Badge>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section
                    id="swaps"
                    title="Trocas"
                    href={swapsIndex()}
                    more={swapCount > swaps.length}
                    empty={
                        swapCount === 0 &&
                        needsReplacement === 0 &&
                        'Nenhuma troca à espera de decisão.'
                    }
                >
                    {needsReplacement > 0 && (
                        <p className="text-sm">
                            {needsReplacement === 1
                                ? '1 turno por substituir.'
                                : `${needsReplacement} turnos por substituir.`}
                        </p>
                    )}
                    {swaps.length > 0 && (
                        <ul className="divide-y rounded-lg border">
                            {swaps.map((swap) => (
                                <li
                                    key={swap.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-3"
                                >
                                    <span>{swap.shift}</span>
                                    <span className="flex gap-2">
                                        {swap.awaitingDecision && (
                                            <Badge>À espera de aprovação</Badge>
                                        )}
                                        {swap.isUrgent && (
                                            <Badge variant="destructive">
                                                Urgente
                                            </Badge>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section
                    id="gaps"
                    title={`Lugares vazios nos próximos ${gapDays} dias`}
                    href={coverage()}
                    more={false}
                    empty={
                        gaps.length === 0 &&
                        'Todos os eventos têm os lugares preenchidos.'
                    }
                >
                    <ul className="space-y-2">
                        {gaps.map((row) => (
                            <CoverageItem key={row.eventId} row={row} />
                        ))}
                    </ul>
                </Section>
            </div>
        </>
    );
}

function Tile({
    href,
    label,
    value,
}: {
    href: ReturnType<typeof dashboard>;
    label: string;
    value: number;
}) {
    return (
        <Link
            href={href}
            className="rounded-lg border p-4 transition-colors hover:bg-muted/50"
        >
            <p className="text-3xl font-semibold tabular-nums">{value}</p>
            <p className="text-sm text-muted-foreground">{label}</p>
        </Link>
    );
}

function Section({
    id,
    title,
    href,
    more,
    empty,
    children,
}: {
    id: string;
    title: string;
    href: ReturnType<typeof dashboard>;
    more: boolean;
    empty: string | false;
    children: ReactNode;
}) {
    return (
        <section aria-labelledby={id} className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <h2 id={id} className="font-medium">
                    {title}
                </h2>
                <Link
                    href={href}
                    className="inline-flex min-h-11 items-center text-sm underline-offset-4 hover:underline"
                >
                    {more ? 'Ver todos' : 'Abrir'}
                </Link>
            </div>
            {empty ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                children
            )}
        </section>
    );
}

CoordinatorDashboard.layout = {
    breadcrumbs: [{ title: 'Painel', href: dashboard() }],
};
