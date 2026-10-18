import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { axe } from 'vitest-axe';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable, dataTableColumns } from '@/components/app/data-table';

type Person = { name: string; role: string };

const people: Person[] = [
    { name: 'Ana Machava', role: 'Áudio' },
    { name: 'João Sitoe', role: 'Streaming' },
];

const helper = dataTableColumns<Person>();
const columns = helper.columns([
    helper.accessor('name', { header: 'Nome' }),
    helper.accessor('role', { header: 'Função' }),
]);

describe('ConfirmDialog', () => {
    it('states who is affected and confirms only on the confirm button', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();

        render(
            <ConfirmDialog
                title="Publicar escala?"
                description="12 voluntários serão notificados."
                confirmLabel="Publicar"
                onConfirm={onConfirm}
            >
                <button type="button">Publicar escala</button>
            </ConfirmDialog>,
        );

        await user.click(
            screen.getByRole('button', { name: 'Publicar escala' }),
        );
        const dialog = screen.getByRole('dialog', { name: 'Publicar escala?' });
        expect(
            within(dialog).getByText('12 voluntários serão notificados.'),
        ).toBeInTheDocument();
        expect(await axe(dialog)).toHaveNoViolations();

        await user.click(
            within(dialog).getByRole('button', { name: 'Cancelar' }),
        );
        expect(onConfirm).not.toHaveBeenCalled();

        await user.click(
            screen.getByRole('button', { name: 'Publicar escala' }),
        );
        await user.click(screen.getByRole('button', { name: 'Publicar' }));
        expect(onConfirm).toHaveBeenCalledOnce();
    });
});

describe('DataTable', () => {
    it('filters rows by search text and renders accessible markup', async () => {
        const user = userEvent.setup();

        const { container } = render(
            <DataTable
                label="Voluntários"
                columns={columns}
                data={people}
                renderCard={(row) => <p>{row.original.name}</p>}
            />,
        );

        const table = screen.getByRole('table', { name: 'Voluntários' });
        expect(within(table).getByText('Ana Machava')).toBeInTheDocument();

        await user.type(screen.getByRole('searchbox'), 'stream');

        expect(
            within(table).queryByText('Ana Machava'),
        ).not.toBeInTheDocument();
        expect(within(table).getByText('João Sitoe')).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('shows the empty message when nothing matches', async () => {
        const user = userEvent.setup();

        render(
            <DataTable
                label="Voluntários"
                columns={columns}
                data={people}
                renderCard={(row) => <p>{row.original.name}</p>}
                emptyMessage="Nenhum voluntário encontrado."
            />,
        );

        await user.type(screen.getByRole('searchbox'), 'zzz');

        expect(
            screen.getAllByText('Nenhum voluntário encontrado.').length,
        ).toBeGreaterThan(0);
    });
});
