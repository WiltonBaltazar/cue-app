import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { axe } from 'vitest-axe';
import RegistrationCreate from '@/pages/registration/create';

// A small stand-in for Inertia's useForm: the page only needs the data and setters.
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState(initial);

        return {
            data,
            setData: (key: keyof T | ((current: T) => T), value?: unknown) =>
                setDataState((current) =>
                    typeof key === 'function'
                        ? key(current)
                        : { ...current, [key]: value },
                ),
            errors: {},
            processing: false,
            transform: vi.fn(),
            post: vi.fn(),
        };
    },
}));

const props = {
    declaration: { version: 1, body: 'Declaro que as informações...' },
    teamRoles: [
        { id: 1, name: 'Câmara', description: null },
        { id: 2, name: 'Áudio', description: null },
    ],
    levels: [
        { value: 'beginner', label: 'Iniciante' },
        { value: 'mentor', label: 'Mentor' },
    ],
    frequencies: [{ value: 'monthly', label: 'Uma vez por mês' }],
    periods: [{ value: 'morning', label: 'Manhã' }],
    weekdays: [{ value: 7, label: 'Domingo', hint: 'Culto' }],
    trainingAvailabilities: [{ value: 'regular', label: 'Sim, regularmente' }],
    socialNetworks: ['Facebook', 'Instagram'],
    honeypot: {
        enabled: false,
        nameFieldName: '',
        validFromFieldName: '',
        encryptedValidFrom: '',
    },
    turnstileSiteKey: null,
};

function yearsAgo(years: number): string {
    const date = new Date();
    date.setFullYear(date.getFullYear() - years);

    return date.toISOString().slice(0, 10);
}

describe('registration form steps', () => {
    beforeEach(() => sessionStorage.clear());

    it('starts on the first step with an accessible form', async () => {
        const { container } = render(<RegistrationCreate {...props} />);

        expect(
            screen.getByText('Passo 1 de 9: Identificação'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Voltar' }),
        ).not.toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('explains what is missing instead of moving on', async () => {
        const user = userEvent.setup();
        render(<RegistrationCreate {...props} />);

        await user.click(screen.getByRole('button', { name: 'Continuar' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Indique o seu nome completo.',
        );
        expect(
            screen.getByText('Passo 1 de 9: Identificação'),
        ).toBeInTheDocument();
    });

    it('asks a minor for a guardian, then moves on and back', async () => {
        const user = userEvent.setup();
        const { container } = render(<RegistrationCreate {...props} />);

        await user.type(screen.getByLabelText(/Nome completo/), 'Ana Machava');
        fireEvent.change(screen.getByLabelText(/Data de nascimento/), {
            target: { value: yearsAgo(16) },
        });
        await user.click(screen.getByRole('button', { name: 'Continuar' }));

        expect(screen.getByRole('alert')).toHaveTextContent('encarregado');

        await user.type(
            screen.getByLabelText(/Nome do encarregado/),
            'Rosa Machava',
        );
        await user.type(
            screen.getByLabelText(/Telefone do encarregado/),
            '841234567',
        );
        await user.click(screen.getByRole('button', { name: 'Continuar' }));

        expect(
            screen.getByText('Passo 2 de 9: Contactos e conta'),
        ).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();

        await user.click(screen.getByRole('button', { name: 'Voltar' }));
        expect(screen.getByLabelText(/Nome completo/)).toHaveValue(
            'Ana Machava',
        );
    });

    it('turns away people under 14', async () => {
        const user = userEvent.setup();
        render(<RegistrationCreate {...props} />);

        await user.type(screen.getByLabelText(/Nome completo/), 'Ana Machava');
        fireEvent.change(screen.getByLabelText(/Data de nascimento/), {
            target: { value: yearsAgo(12) },
        });
        await user.click(screen.getByRole('button', { name: 'Continuar' }));

        expect(screen.getByRole('alert')).toHaveTextContent('14 anos');
    });

    it('keeps a draft without the sensitive answers', async () => {
        const user = userEvent.setup();
        render(<RegistrationCreate {...props} />);

        await user.type(screen.getByLabelText(/Nome completo/), 'Ana Machava');
        fireEvent.change(screen.getByLabelText(/Data de nascimento/), {
            target: { value: yearsAgo(30) },
        });

        const draft = JSON.parse(
            sessionStorage.getItem('cue.registration-draft') ?? '{}',
        );
        expect(draft.data.full_name).toBe('Ana Machava');
        expect(draft.data).not.toHaveProperty('birth_date');
        expect(draft.data).not.toHaveProperty('password');
    });
});
