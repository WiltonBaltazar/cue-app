import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { axe } from 'vitest-axe';
import { NextShift } from '@/components/app/next-shift';
import { PushPrompt } from '@/components/app/push-prompt';
import { ShiftCard } from '@/components/app/shift-card';
import type { Shift } from '@/components/app/shift-card';
import type { PushState } from '@/hooks/use-push-subscription';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

const push = vi.hoisted(() => ({
    state: 'available' as PushState,
    subscribe: vi.fn(),
}));

vi.mock('@/hooks/use-push-subscription', () => ({
    usePushSubscription: () => ({
        state: push.state,
        busy: false,
        subscribe: push.subscribe,
    }),
}));

const shift: Shift = {
    id: 7,
    scheduleId: 3,
    eventId: 11,
    date: '2026-11-01',
    eventName: 'Culto de domingo',
    role: 'Câmara',
    arrivalTime: '07:30',
    startTime: '08:30',
    endTime: '11:00',
    location: 'Templo central',
    status: 'confirmed',
    statusLabel: 'Confirmado',
    eventCancelled: false,
    canAct: true,
    hasActiveSwap: false,
};

function renderShift(props: Partial<Parameters<typeof ShiftCard>[0]> = {}) {
    const client = new QueryClient();

    return render(
        <QueryClientProvider client={client}>
            <ShiftCard shift={shift} actions {...props} />
        </QueryClientProvider>,
    );
}

describe('ShiftCard decline button', () => {
    beforeEach(() => post.mockReset());

    it('asks for confirmation before telling the coordinators', async () => {
        const user = userEvent.setup();
        const { container } = renderShift();

        expect(screen.getByText('07:30')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Não posso ir' }),
        ).not.toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();

        // The row opens to show the details and actions.
        await user.click(
            screen.getByRole('button', { name: /Culto de domingo/ }),
        );
        expect(screen.getByText('Templo central')).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();

        await user.click(screen.getByRole('button', { name: 'Não posso ir' }));
        const dialog = screen.getByRole('dialog', { name: 'Não pode ir?' });
        expect(
            within(dialog).getByText(/Culto de domingo/),
        ).toBeInTheDocument();
        expect(await axe(dialog)).toHaveNoViolations();

        await user.click(
            within(dialog).getByRole('button', { name: 'Cancelar' }),
        );
        expect(post).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Não posso ir' }));
        await user.click(
            within(
                screen.getByRole('dialog', { name: 'Não pode ir?' }),
            ).getByRole('button', { name: 'Não posso ir' }),
        );
        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toContain('/7/');
    });

    it('opens the confirmation straight away from the 72 h check push', () => {
        renderShift({ declineOpen: true });

        expect(
            screen.getByRole('dialog', { name: 'Não pode ir?' }),
        ).toBeInTheDocument();
    });

    it('hides the actions when the volunteer cannot act on the shift', () => {
        renderShift({ shift: { ...shift, canAct: false } });

        expect(
            screen.queryByRole('button', { name: 'Não posso ir' }),
        ).not.toBeInTheDocument();
    });
});

describe('NextShift', () => {
    const team = {
        people: [
            { name: 'Ana M.', role: 'Câmara', isMe: true },
            { name: 'João S.', role: 'Câmara', isMe: false },
            { name: 'Rita C.', role: 'Câmara', isMe: false },
            { name: 'Paulo N.', role: 'Áudio', isMe: false },
        ],
        needsReplacement: 1,
    };

    function renderNext(props: Partial<Parameters<typeof NextShift>[0]> = {}) {
        return render(
            <QueryClientProvider client={new QueryClient()}>
                <NextShift shift={shift} team={team} {...props} />
            </QueryClientProvider>,
        );
    }

    it('leads with the arrival time and lists the rest of the team by role', async () => {
        const { container } = renderNext();

        expect(screen.getByText('07:30')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Culto de domingo' }),
        ).toBeInTheDocument();
        expect(screen.getByText('João S., Rita C.')).toBeInTheDocument();
        expect(screen.getByText('Paulo N.')).toBeInTheDocument();
        expect(screen.queryByText(/Ana M\./)).not.toBeInTheDocument();
        expect(screen.getByText('1 lugar por substituir')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Não posso ir' }),
        ).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('says when a substitute is being looked for, without the actions', () => {
        renderNext({
            shift: { ...shift, status: 'needs_replacement', canAct: false },
        });

        expect(screen.getByText('À procura de substituto')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Pedir troca' }),
        ).not.toBeInTheDocument();
    });
});

describe('PushPrompt', () => {
    beforeEach(() => {
        push.state = 'available';
        push.subscribe.mockReset();
        localStorage.clear();
    });

    afterEach(() => localStorage.clear());

    it('offers to turn on notifications', async () => {
        const user = userEvent.setup();
        push.subscribe.mockResolvedValue(true);
        const { container } = render(<PushPrompt />);

        expect(
            screen.getByRole('heading', { name: 'Receba os avisos da escala' }),
        ).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();

        await user.click(
            screen.getByRole('button', { name: 'Activar notificações' }),
        );
        expect(push.subscribe).toHaveBeenCalledOnce();
    });

    it('explains adding to the home screen on iPhone', async () => {
        push.state = 'needs-install';
        const { container } = render(<PushPrompt />);

        expect(
            screen.getByText('Adicionar ao ecrã principal'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Activar notificações' }),
        ).not.toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('stays hidden for 30 days after "Agora não"', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<PushPrompt />);

        await user.click(screen.getByRole('button', { name: 'Agora não' }));
        expect(
            screen.queryByText('Receba os avisos da escala'),
        ).not.toBeInTheDocument();

        unmount();
        render(<PushPrompt />);
        expect(
            screen.queryByText('Receba os avisos da escala'),
        ).not.toBeInTheDocument();
    });

    it('renders nothing once the device is subscribed', () => {
        push.state = 'subscribed';
        const { container } = render(<PushPrompt />);

        expect(container).toBeEmptyDOMElement();
    });
});
