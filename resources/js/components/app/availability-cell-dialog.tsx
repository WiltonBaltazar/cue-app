import { Link } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/lib/query-client';
import { availabilityPeople } from '@/routes/app-api';
import { history } from '@/routes/coord/volunteers';

type Person = {
    volunteerId: number;
    name: string;
    variableHours: boolean;
    roles: string[];
};

/**
 * A count of the availability table that opens the names behind it, with the same filters.
 */
export function AvailabilityCellDialog({
    count,
    weekday,
    period,
    title,
    filter,
    role,
    minLevel,
}: {
    count: number;
    weekday: number;
    period: string;
    /** e.g. "Domingo, manhã" */
    title: string;
    /** The role and level filter in words, or the default. */
    filter: string;
    role?: string;
    minLevel?: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="inline-flex min-h-11 min-w-11 cursor-pointer items-center rounded-md px-1 text-lg font-semibold tabular-nums underline decoration-dotted underline-offset-4 hover:decoration-solid focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {count}
                    <span className="sr-only"> pessoas: ver nomes</span>
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{filter}</DialogDescription>
                </DialogHeader>
                <People
                    weekday={weekday}
                    period={period}
                    role={role}
                    minLevel={minLevel}
                />
            </DialogContent>
        </Dialog>
    );
}

function People(filters: {
    weekday: number;
    period: string;
    role?: string;
    minLevel?: string;
}) {
    const query = useQuery({
        queryKey: ['availability-people', filters],
        queryFn: () =>
            apiFetch<{ data: Person[] }>(
                availabilityPeople({ query: filters }).url,
            ).then((body) => body.data),
    });

    if (query.isPending) {
        return (
            <p className="flex items-center gap-2 text-muted-foreground">
                <Spinner /> A carregar…
            </p>
        );
    }

    if (query.isError) {
        return (
            <p className="text-sm text-destructive">
                Não foi possível carregar os nomes.
            </p>
        );
    }

    return (
        <ul className="divide-y rounded-lg border">
            {query.data.map((person) => (
                <li key={person.volunteerId} className="px-3 py-2">
                    <Link
                        href={history(person.volunteerId)}
                        className="inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline"
                    >
                        {person.name}
                    </Link>
                    {person.variableHours && (
                        <Badge variant="outline" className="ml-2">
                            Horários variáveis
                        </Badge>
                    )}
                    <p className="text-sm text-muted-foreground">
                        {person.roles.length > 0
                            ? person.roles.join(', ')
                            : 'Sem funções'}
                    </p>
                </li>
            ))}
        </ul>
    );
}
