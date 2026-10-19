import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeAll,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { axe } from 'vitest-axe';
import {
    InstallAppInstructions,
    InstallAppPrompt,
} from '@/components/app/install-app';
import { listenForInstallPrompt } from '@/hooks/use-install-prompt';

const ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';
const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

function useAgent(agent: string) {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue(agent);
}

function offerInstall() {
    const prompt = vi.fn().mockResolvedValue(undefined);
    const event = Object.assign(new Event('beforeinstallprompt'), {
        prompt,
        userChoice: Promise.resolve({ outcome: 'accepted' as const }),
    });

    act(() => {
        window.dispatchEvent(event);
    });

    return prompt;
}

beforeAll(() => {
    window.matchMedia ??= vi.fn().mockReturnValue({ matches: false });
    listenForInstallPrompt();
});

beforeEach(() => localStorage.clear());

afterEach(() => vi.restoreAllMocks());

describe('InstallAppPrompt', () => {
    it('gives Android users the Chrome menu steps until the browser offers to install', async () => {
        useAgent(ANDROID);
        const { container } = render(<InstallAppPrompt />);

        expect(
            screen.getByRole('heading', {
                name: 'Instale a Cue App no telemóvel',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Instalar app')).toBeInTheDocument();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('installs with one tap once the browser allows it', async () => {
        const user = userEvent.setup();
        useAgent(ANDROID);
        render(<InstallAppPrompt />);

        const prompt = offerInstall();
        await user.click(
            screen.getByRole('button', { name: 'Instalar a app' }),
        );

        expect(prompt).toHaveBeenCalledOnce();
    });

    it('is left to the push prompt on iPhone', () => {
        useAgent(IPHONE);
        const { container } = render(<InstallAppPrompt />);

        expect(container).toBeEmptyDOMElement();
    });

    it('stays hidden after "Agora não"', async () => {
        const user = userEvent.setup();
        useAgent(ANDROID);
        const { unmount } = render(<InstallAppPrompt />);

        await user.click(screen.getByRole('button', { name: 'Agora não' }));
        unmount();
        const { container } = render(<InstallAppPrompt />);

        expect(container).toBeEmptyDOMElement();
    });
});

describe('InstallAppInstructions', () => {
    it('explains Safari "Adicionar ao ecrã principal" on iPhone', () => {
        useAgent(IPHONE);
        render(<InstallAppInstructions />);

        expect(
            screen.getByText('Adicionar ao ecrã principal'),
        ).toBeInTheDocument();
    });
});
