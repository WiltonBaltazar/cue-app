import { Head, Link, router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import { audit } from '@/routes/coord';

type Change = { field: string; old: string | null; new: string | null };

type Entry = {
    id: number;
    at: string | null;
    actor: string;
    action: string;
    subjectType: string | null;
    subject: string | null;
    changes: Change[];
};

type Props = {
    entries: Entry[];
    pagination: {
        currentPage: number;
        lastPage: number;
        total: number;
        previousUrl: string | null;
        nextUrl: string | null;
    };
    filter: string | null;
    filters: { value: string; label: string }[];
};

const ALL = 'all';

/**
 * Read-only audit log (FR-029, FR-035). Volunteer names are looked up when the page
 * loads; anonymised people appear as "Voluntário #id".
 */
export default function CoordinatorAudit({
    entries,
    pagination,
    filter,
    filters,
}: Props) {
    const changeFilter = (value: string) =>
        router.get(
            audit().url,
            value === ALL ? {} : { tipo: value },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Auditoria" />

            <div className="space-y-4 p-4 lg:max-w-5xl">
                <div>
                    <h1 className="text-2xl font-semibold">Auditoria</h1>
                    <p className="text-muted-foreground">
                        Quem alterou o quê, e quando. O registo não pode ser
                        editado nem apagado.
                    </p>
                </div>

                <div className="grid w-full gap-2 sm:w-72">
                    <Label htmlFor="audit-filter">Mostrar</Label>
                    <Select value={filter ?? ALL} onValueChange={changeFilter}>
                        <SelectTrigger id="audit-filter" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tudo</SelectItem>
                            {filters.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {entries.length === 0 ? (
                    <p className="rounded-xl border p-6 text-center text-muted-foreground">
                        Ainda não há registos.
                    </p>
                ) : (
                    <ol className="divide-y rounded-xl border">
                        {entries.map((entry) => (
                            <li key={entry.id} className="space-y-1 p-4">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <p>
                                        <span className="font-medium">
                                            {entry.actor}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {entry.action.toLowerCase()}
                                        </span>
                                        {entry.subject && (
                                            <>
                                                {' '}
                                                {entry.subjectType && (
                                                    <span className="text-muted-foreground">
                                                        {entry.subjectType.toLowerCase()}{' '}
                                                    </span>
                                                )}
                                                <span className="font-medium">
                                                    {entry.subject}
                                                </span>
                                            </>
                                        )}
                                    </p>
                                    {entry.at && (
                                        <time
                                            dateTime={entry.at}
                                            className="text-sm text-muted-foreground tabular-nums"
                                        >
                                            {formatDateTime(entry.at)}
                                        </time>
                                    )}
                                </div>
                                {entry.changes.length > 0 && (
                                    <ul className="space-y-0.5 text-sm text-muted-foreground">
                                        {entry.changes.map((change) => (
                                            <li
                                                key={change.field}
                                                className="break-words"
                                            >
                                                <code className="text-foreground">
                                                    {change.field}
                                                </code>
                                                {': '}
                                                {change.old !== null && (
                                                    <>{change.old} → </>
                                                )}
                                                {change.new ?? '—'}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                {pagination.lastPage > 1 && (
                    <nav
                        aria-label="Páginas"
                        className="flex items-center justify-between gap-4"
                    >
                        {pagination.previousUrl ? (
                            <Link
                                href={pagination.previousUrl}
                                preserveScroll
                                className="inline-flex min-h-11 items-center rounded-md border px-4"
                            >
                                Mais recentes
                            </Link>
                        ) : (
                            <span />
                        )}
                        <span className="text-sm text-muted-foreground">
                            Página {pagination.currentPage} de{' '}
                            {pagination.lastPage}
                        </span>
                        {pagination.nextUrl ? (
                            <Link
                                href={pagination.nextUrl}
                                preserveScroll
                                className="inline-flex min-h-11 items-center rounded-md border px-4"
                            >
                                Mais antigos
                            </Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

CoordinatorAudit.layout = {
    breadcrumbs: [{ title: 'Auditoria', href: audit() }],
};
