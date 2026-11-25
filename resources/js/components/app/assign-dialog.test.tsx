import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AssignDialog } from '@/components/app/assign-dialog';
import { apiFetch } from '@/lib/query-client';

vi.mock('@/lib/query-client', () => ({ apiFetch: vi.fn() }));
const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({ router: { post, put: vi.fn() } }));

const person = (volunteerId: number, name: string, violations: string[]) => ({
    volunteerId,
    name,
    level: violations.includes('role') ? null : 'Intermédio',
    recentAssignments: 0,
    eligible: violations.length === 0,
    violations,
});

const show = () =>
    render(
        <QueryClientProvider
            client={
                new QueryClient({
                    defaultOptions: { queries: { retry: false } },
                })
            }
        >
            <AssignDialog
                target={{ positionId: 1, label: 'Áudio', replacing: null }}
                onClose={() => {}}
            />
        </QueryClientProvider>,
    );

describe('AssignDialog', () => {
    beforeEach(() => {
        post.mockReset();
        vi.mocked(apiFetch).mockResolvedValue({
            role: 'Áudio',
            data: [person(1, 'Ana Machava', [])],
            others: [
                person(2, 'João Sitoe', ['role']),
                person(3, 'Rita Cossa', ['role', 'absence']),
            ],
        });
    });

    it('saves someone with the role at once', async () => {
        const user = userEvent.setup();
        show();

        await user.click(await screen.findByLabelText(/Ana Machava/));
        await user.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(post.mock.calls[0][1]).toMatchObject({
            volunteer_id: 1,
            add_role: false,
        });
    });

    it('finds people without the role, confirms and can add it', async () => {
        const user = userEvent.setup();
        show();

        await user.click(
            await screen.findByRole('button', { name: /Outras pessoas/ }),
        );
        await user.type(screen.getByLabelText('Procurar por nome'), 'joao');
        expect(screen.queryByLabelText(/Rita Cossa/)).not.toBeInTheDocument();

        await user.click(screen.getByLabelText(/João Sitoe/));
        await user.click(
            screen.getByRole('checkbox', { name: /Acrescentar Áudio/ }),
        );
        await user.click(screen.getByRole('button', { name: 'Guardar' }));
        expect(post).not.toHaveBeenCalled();

        const confirm = screen.getByRole('dialog', {
            name: 'Escalar João Sitoe sem a função?',
        });
        await user.click(
            within(confirm).getByRole('button', { name: 'Escalar' }),
        );

        expect(post.mock.calls[0][1]).toMatchObject({
            volunteer_id: 2,
            add_role: true,
        });
    });
});
