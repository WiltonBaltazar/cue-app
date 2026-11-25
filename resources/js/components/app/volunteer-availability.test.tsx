import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { axe } from 'vitest-axe';
import { NotServingInMonth } from '@/components/app/volunteer-availability';
import { apiFetch } from '@/lib/query-client';

vi.mock('@/lib/query-client', () => ({ apiFetch: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: React.ReactNode }) => (
        <a href="/historico">{children}</a>
    ),
}));

const show = () =>
    render(
        <QueryClientProvider
            client={
                new QueryClient({
                    defaultOptions: { queries: { retry: false } },
                })
            }
        >
            <NotServingInMonth />
        </QueryClientProvider>,
    );

const response = (count: number) => ({
    month: '2026-11',
    events: 9,
    serving: 5,
    notServing: Array.from({ length: count }, (_, index) => ({
        volunteerId: index + 1,
        name: `Pessoa ${index + 1}`,
        reason: 'Em pausa',
    })),
});

describe('NotServingInMonth', () => {
    beforeEach(() => vi.mocked(apiFetch).mockReset());

    it('names the people left out with why, ten at a time', async () => {
        vi.mocked(apiFetch).mockResolvedValue(response(12));
        const { container } = show();

        expect(await screen.findByText('Pessoa 10')).toBeInTheDocument();
        expect(screen.queryByText('Pessoa 11')).not.toBeInTheDocument();
        expect(
            screen.getByText('Sem turno: 12 pessoas. Com turno: 5 pessoas.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Mostrar todas (12)' }),
        );
        expect(screen.getByText('Pessoa 12')).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('says so when everyone has a shift', async () => {
        vi.mocked(apiFetch).mockResolvedValue(response(0));
        show();

        expect(
            await screen.findByText('Todos têm pelo menos um turno neste mês.'),
        ).toBeInTheDocument();
    });
});
