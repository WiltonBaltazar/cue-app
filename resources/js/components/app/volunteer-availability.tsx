import { Link } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { TextField } from '@/components/volunteer-form/fields';
import { DISPLAY_TIME_ZONE } from '@/lib/format';
import { apiFetch } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { availabilityOnDate, volunteersNotServing } from '@/routes/app-api';
import { history } from '@/routes/coord/volunteers';

/** Longer lists start cut to this many rows. */
const SHORT_LIST = 10;

type Person = { volunteerId: number; name: string };

export type OnDate = {
    date: string;
    unavailable: (Person & { reason: string })[];
    available: (Person & { when: string })[];
};

export type NotServing = {
    month: string;
    events: number;
    serving: number;
    notServing: (Person & { reason: string })[];
};

/** "2026-11-02" in Mozambique time. */
function today(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: DISPLAY_TIME_ZONE,
    }).format(new Date());
}

const people = (count: number) =>
    count === 1 ? '1 pessoa' : `${count} pessoas`;

/**
 * Who cannot serve on a chosen date, and why; the available ones below.
 */
export function AvailabilityOnDate() {
    const [date, setDate] = useState(today);
    const query = useQuery({
        queryKey: ['availability-on-date', date],
        queryFn: () =>
            apiFetch<OnDate>(availabilityOnDate({ query: { date } }).url),
        enabled: date !== '',
        placeholderData: (previous) => previous,
    });

    return (
        <Section
            title="Quem não pode numa data"
            description="Ausências, pausas e quem não costuma poder nesse dia da semana."
        >
            <TextField
                label="Data"
                type="date"
                value={date}
                onChange={(event) => setDate(event.target.value)}
                className="w-full sm:w-56"
            />
            <QueryState query={query} />
            {date !== '' && query.data && (
                <div
                    className={cn(
                        'grid gap-6 lg:grid-cols-2',
                        query.isFetching && 'opacity-60',
                    )}
                >
                    <PeopleList
                        title={`Não podem: ${people(query.data.unavailable.length)}`}
                        empty="Ninguém está indisponível nesta data."
                        rows={query.data.unavailable.map((person) => ({
                            ...person,
                            note: person.reason,
                        }))}
                    />
                    <PeopleList
                        title={`Podem: ${people(query.data.available.length)}`}
                        empty="Ninguém está disponível nesta data."
                        rows={query.data.available.map((person) => ({
                            ...person,
                            note: person.when,
                        }))}
                    />
                </div>
            )}
        </Section>
    );
}

/**
 * Who has no shift in a chosen month, and why.
 */
export function NotServingInMonth() {
    const [month, setMonth] = useState(() => today().slice(0, 7));
    const query = useQuery({
        queryKey: ['volunteers-not-serving', month],
        queryFn: () =>
            apiFetch<NotServing>(
                volunteersNotServing({ query: { month } }).url,
            ),
        enabled: month !== '',
        placeholderData: (previous) => previous,
    });

    return (
        <Section
            title="Quem não foi escalado num mês"
            description="Pessoas sem nenhum turno no mês, contando também escalas em rascunho."
        >
            <TextField
                label="Mês"
                type="month"
                value={month}
                onChange={(event) => setMonth(event.target.value)}
                className="w-full sm:w-56"
            />
            <QueryState query={query} />
            {month !== '' &&
                query.data &&
                (query.data.events === 0 ? (
                    <p className="text-muted-foreground">
                        Não há eventos neste mês.
                    </p>
                ) : (
                    <div className={cn(query.isFetching && 'opacity-60')}>
                        <PeopleList
                            title={`Sem turno: ${people(query.data.notServing.length)}. Com turno: ${people(query.data.serving)}.`}
                            empty="Todos têm pelo menos um turno neste mês."
                            rows={query.data.notServing.map((person) => ({
                                ...person,
                                note: person.reason,
                            }))}
                        />
                    </div>
                ))}
        </Section>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    const id = useId();

    return (
        <section aria-labelledby={id} className="space-y-4 border-t pt-6">
            <div>
                <h2 id={id} className="text-lg font-semibold">
                    {title}
                </h2>
                <p className="text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

function QueryState({
    query,
}: {
    query: { isError: boolean; isLoading: boolean; error: Error | null };
}) {
    if (query.isError) {
        return (
            <Alert variant="destructive">
                <AlertTitle>Não foi possível carregar</AlertTitle>
                <AlertDescription>{query.error?.message}</AlertDescription>
            </Alert>
        );
    }

    return query.isLoading ? (
        <p className="flex items-center gap-2 text-muted-foreground">
            <Spinner /> A carregar…
        </p>
    ) : null;
}

function PeopleList({
    title,
    empty,
    rows,
}: {
    title: string;
    empty: string;
    rows: (Person & { note: string })[];
}) {
    const [expanded, setExpanded] = useState(false);
    const shown = expanded ? rows : rows.slice(0, SHORT_LIST);

    return (
        <div className="space-y-2">
            <h3 className="font-medium" aria-live="polite">
                {title}
            </h3>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {shown.map((row) => (
                        <li
                            key={row.volunteerId}
                            className="px-3 py-2 sm:flex sm:items-center sm:justify-between sm:gap-4"
                        >
                            <Link
                                href={history(row.volunteerId)}
                                className="inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline"
                            >
                                {row.name}
                            </Link>
                            <p className="text-sm text-muted-foreground sm:text-right">
                                {row.note}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            {rows.length > SHORT_LIST && (
                <Button
                    variant="ghost"
                    className="min-h-11"
                    aria-expanded={expanded}
                    onClick={() => setExpanded(!expanded)}
                >
                    {expanded
                        ? 'Mostrar menos'
                        : `Mostrar todas (${rows.length})`}
                </Button>
            )}
        </div>
    );
}
