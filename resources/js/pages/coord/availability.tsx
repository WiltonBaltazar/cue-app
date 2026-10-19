import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { availabilityMatrix } from '@/routes/app-api';
import { availability } from '@/routes/coord';

type Option = { value: string; label: string };

type Matrix = {
    weekdays: number[];
    periods: string[];
    counts: Record<string, number>;
    demand: Record<string, number>;
    shortages: string[];
    shortageCells: Record<string, string[]>;
};

const ALL = 'all';

/**
 * Volunteers usually available per weekday × period, filterable by role and level, with
 * the roles that have more places than people flagged "em falta" (US5).
 */
export default function Availability({
    roles,
    levels,
    weekdays,
    periods,
}: {
    roles: { name: string; slug: string }[];
    levels: Option[];
    weekdays: Record<number, string>;
    periods: Record<string, string>;
}) {
    const [role, setRole] = useState(ALL);
    const [minLevel, setMinLevel] = useState(ALL);

    const query = useQuery({
        queryKey: ['availability-matrix', role, minLevel],
        queryFn: () =>
            apiFetch<Matrix>(
                availabilityMatrix({
                    query: {
                        role: role === ALL ? undefined : role,
                        minLevel:
                            role === ALL || minLevel === ALL
                                ? undefined
                                : minLevel,
                    },
                }).url,
            ),
        placeholderData: (previous) => previous,
    });

    const roleName = (slug: string) =>
        roles.find((item) => item.slug === slug)?.name ?? slug;
    const matrix = query.data;

    return (
        <>
            <Head title="Disponibilidade" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Disponibilidade</h1>
                    <p className="text-muted-foreground">
                        Quantos voluntários activos estão normalmente
                        disponíveis em cada dia e período.
                    </p>
                </div>

                <div className="flex flex-wrap gap-4">
                    <div className="grid w-full gap-2 sm:w-56">
                        <Label htmlFor="role">Função</Label>
                        <Select value={role} onValueChange={setRole}>
                            <SelectTrigger id="role" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Todas</SelectItem>
                                {roles.map((item) => (
                                    <SelectItem
                                        key={item.slug}
                                        value={item.slug}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid w-full gap-2 sm:w-56">
                        <Label htmlFor="min-level">Nível mínimo</Label>
                        <Select
                            value={minLevel}
                            onValueChange={setMinLevel}
                            disabled={role === ALL}
                        >
                            <SelectTrigger id="min-level" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Qualquer</SelectItem>
                                {levels.map((level) => (
                                    <SelectItem
                                        key={level.value}
                                        value={level.value}
                                    >
                                        {level.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {query.isError && (
                    <Alert variant="destructive">
                        <AlertTitle>Não foi possível carregar</AlertTitle>
                        <AlertDescription>
                            {query.error.message}
                        </AlertDescription>
                    </Alert>
                )}

                {query.isPending && (
                    <p className="flex items-center gap-2 text-muted-foreground">
                        <Spinner /> A carregar…
                    </p>
                )}

                {matrix && (
                    <>
                        {matrix.shortages.length > 0 ? (
                            <Alert>
                                <TriangleAlert />
                                <AlertTitle>Funções em falta</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        Há eventos nos próximos 60 dias que
                                        precisam de mais pessoas do que as
                                        disponíveis:
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {matrix.shortages.map((slug) => (
                                            <Badge
                                                key={slug}
                                                variant="destructive"
                                            >
                                                {roleName(slug)} — em falta
                                            </Badge>
                                        ))}
                                    </div>
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma função em falta nos próximos 60 dias.
                            </p>
                        )}

                        <div
                            className={cn(
                                'overflow-x-auto rounded-lg border',
                                query.isFetching && 'opacity-60',
                            )}
                        >
                            <table className="w-full min-w-[36rem] text-sm">
                                <caption className="sr-only">
                                    Voluntários disponíveis por dia e período
                                </caption>
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th
                                            scope="col"
                                            className="p-3 text-left font-medium"
                                        >
                                            Dia
                                        </th>
                                        {matrix.periods.map((period) => (
                                            <th
                                                key={period}
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                {periods[period]}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {matrix.weekdays.map((weekday) => (
                                        <tr
                                            key={weekday}
                                            className="border-b last:border-0"
                                        >
                                            <th
                                                scope="row"
                                                className="p-3 text-left font-medium"
                                            >
                                                {weekdays[weekday]}
                                            </th>
                                            {matrix.periods.map((period) => {
                                                const key = `${weekday}.${period}`;

                                                return (
                                                    <MatrixCell
                                                        key={key}
                                                        count={
                                                            matrix.counts[
                                                                key
                                                            ] ?? 0
                                                        }
                                                        demand={
                                                            matrix.demand[key]
                                                        }
                                                        shortRoles={(
                                                            matrix
                                                                .shortageCells[
                                                                key
                                                            ] ?? []
                                                        ).map(roleName)}
                                                    />
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            "Precisa" é o maior número de pessoas que um evento
                            {role === ALL
                                ? ''
                                : ` pede em ${roleName(role)}`}{' '}
                            nesse dia e período, nos próximos 60 dias.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

function MatrixCell({
    count,
    demand,
    shortRoles,
}: {
    count: number;
    demand: number | undefined;
    shortRoles: string[];
}) {
    const short = shortRoles.length > 0;

    return (
        <td
            className={cn(
                'p-3 align-top',
                short && 'bg-destructive/10',
                count === 0 && !demand && 'text-muted-foreground',
            )}
        >
            <span className="text-lg font-semibold tabular-nums">{count}</span>
            {demand !== undefined && (
                <span className="ml-1 text-muted-foreground">
                    · precisa {demand}
                </span>
            )}
            {short && (
                <span className="mt-1 block text-xs font-medium text-destructive">
                    Em falta: {shortRoles.join(', ')}
                </span>
            )}
        </td>
    );
}

Availability.layout = {
    breadcrumbs: [{ title: 'Disponibilidade', href: availability() }],
};
