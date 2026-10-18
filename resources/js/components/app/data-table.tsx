import {
    columnFilteringFeature,
    createColumnHelper,
    createFilteredRowModel,
    createSortedRowModel,
    filterFn_includesString,
    globalFilteringFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import type { ColumnDef, Row, RowData } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * TanStack Table v9 registers features explicitly; every coordinator grid uses
 * client-side sorting and text search.
 */
export const dataTableFeatures = tableFeatures({
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
    columnFilteringFeature,
    globalFilteringFeature,
    filteredRowModel: createFilteredRowModel(),
    filterFns: { includesString: filterFn_includesString },
});

type Features = typeof dataTableFeatures;

export type DataTableColumn<TData extends RowData> = ColumnDef<Features, TData>;
export type DataTableRow<TData extends RowData> = Row<Features, TData>;

/** Column helper typed for DataTable; define columns at module scope (stable input). */
export function dataTableColumns<TData extends RowData>() {
    return createColumnHelper<Features, TData>();
}

type Props<TData extends RowData> = {
    columns: DataTableColumn<TData>[];
    data: TData[];
    /** Accessible name for the table, e.g. "Cadastros pendentes". */
    label: string;
    /** On phones each row is shown as a card instead of a table row (FR-030). */
    renderCard: (row: DataTableRow<TData>) => ReactNode;
    searchPlaceholder?: string;
    emptyMessage?: string;
};

/**
 * Coordinator data grid: sorting and text search, a shadcn table on wide
 * screens and stacked cards on phones.
 */
export function DataTable<TData extends RowData>({
    columns,
    data,
    label,
    renderCard,
    searchPlaceholder = 'Pesquisar…',
    emptyMessage = 'Sem resultados.',
}: Props<TData>) {
    // The table instance is mutable; the React Compiler must not memoise reads from it.
    'use no memo';

    const table = useTable({
        features: dataTableFeatures,
        columns,
        data,
        globalFilterFn: 'includesString',
    });

    const rows = table.getRowModel().rows;
    const globalFilter = String(table.state.globalFilter ?? '');

    return (
        <div className="space-y-4">
            <Input
                type="search"
                value={globalFilter}
                onChange={(event) => table.setGlobalFilter(event.target.value)}
                placeholder={searchPlaceholder}
                aria-label={searchPlaceholder}
                className="min-h-11 max-w-sm"
            />

            <ul aria-label={label} className="space-y-3 md:hidden">
                {rows.map((row) => (
                    <li key={row.id} className="rounded-lg border p-4">
                        {renderCard(row)}
                    </li>
                ))}
                {rows.length === 0 && (
                    <li className="p-4 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </li>
                )}
            </ul>

            <div className="hidden rounded-lg border md:block">
                <Table aria-label={label}>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const sorted = header.column.getIsSorted();

                                    if (header.isPlaceholder) {
                                        return <TableHead key={header.id} />;
                                    }

                                    return (
                                        <TableHead
                                            key={header.id}
                                            aria-sort={
                                                sorted === 'asc'
                                                    ? 'ascending'
                                                    : sorted === 'desc'
                                                      ? 'descending'
                                                      : undefined
                                            }
                                        >
                                            {header.column.getCanSort() ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="-ml-2"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                    {sorted === 'asc' ? (
                                                        <ArrowUp aria-hidden="true" />
                                                    ) : sorted === 'desc' ? (
                                                        <ArrowDown aria-hidden="true" />
                                                    ) : (
                                                        <ArrowUpDown
                                                            aria-hidden="true"
                                                            className="opacity-50"
                                                        />
                                                    )}
                                                </Button>
                                            ) : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {row.getAllCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-20 text-center text-muted-foreground"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
