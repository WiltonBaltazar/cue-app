import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { axe } from 'vitest-axe';

describe('test setup', () => {
    it('renders components and runs accessibility checks', async () => {
        const { container } = render(
            <button type="button">Não posso ir</button>,
        );

        expect(
            screen.getByRole('button', { name: 'Não posso ir' }),
        ).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });
});
