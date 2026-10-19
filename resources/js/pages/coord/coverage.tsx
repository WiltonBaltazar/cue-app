import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { CoverageItem } from '@/components/app/coverage-item';
import type { CoverageRow } from '@/components/app/coverage-item';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { DISPLAY_TIME_ZONE } from '@/lib/format';
import { apiFetch } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { coverage as coverageApi } from '@/routes/app-api';
import { coverage } from '@/routes/coord';

const RANGES = [30, 60, 90] as const;

/** "2026-11-02" in Mozambique time, `days` from today. */
function localDate(days: number): string {
    const date = new Date(Date.now() + days * 86_400_000);

    return new Intl.DateTimeFormat('en-CA', {
        timeZone: DISPLAY_TIME_ZONE,
    }).format(date);
}

/**
 * Filled vs required places for each upcoming event, with the roles still missing
 * people (US5).
 */
export default function Coverage() {
    const [days, setDays] = useState<(typeof RANGES)[number]>(30);

    const query = useQuery({
        queryKey: ['coverage', days],
        queryFn: () =>
            apiFetch<{ data: CoverageRow[] }>(
                coverageApi({
                    query: { from: localDate(0), to: localDate(days) },
                }).url,
            ).then((body) => body.data),
        placeholderData: (previous) => previous,
    });

    const rows = query.data ?? [];
    const withGaps = rows.filter((row) => row.filled < row.required);

    return (
        <>
            <Head title="Cobertura" />

            <div className="space-y-6 p-4 lg:max-w-3xl">
                <div>
                    <h1 className="text-2xl font-semibold">Cobertura</h1>
                    <p className="text-muted-foreground">
                        Lugares preenchidos e em falta em cada evento.
                    </p>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={String(days)}
                    onValueChange={(value) =>
                        value &&
                        setDays(Number(value) as (typeof RANGES)[number])
                    }
                    aria-label="Período"
                >
                    {RANGES.map((range) => (
                        <ToggleGroupItem
                            key={range}
                            value={String(range)}
                            className="min-h-11 px-4"
                        >
                            Próximos {range} dias
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>

                {query.isError && (
                    <Alert variant="destructive">
                        <AlertTitle>Não foi possível carregar</AlertTitle>
                        <AlertDescription>
                            {query.error.message}
                        </AlertDescription>
                    </Alert>
                )}

                {query.isPending ? (
                    <p className="flex items-center gap-2 text-muted-foreground">
                        <Spinner /> A carregar…
                    </p>
                ) : (
                    <>
                        <p className="font-medium" aria-live="polite">
                            {rows.length === 0
                                ? 'Não há eventos neste período.'
                                : withGaps.length === 0
                                  ? `${rows.length} eventos, todos com os lugares preenchidos.`
                                  : `${withGaps.length} de ${rows.length} eventos com lugares por preencher.`}
                        </p>
                        <ul
                            className={cn(
                                'space-y-2',
                                query.isFetching && 'opacity-60',
                            )}
                        >
                            {rows.map((row) => (
                                <CoverageItem key={row.eventId} row={row} />
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}

Coverage.layout = {
    breadcrumbs: [{ title: 'Cobertura', href: coverage() }],
};
