import { useCallback, useSyncExternalStore } from 'react';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/**
 * - `android`: Android phone or tablet (Chrome, Edge, Samsung Internet…).
 * - `ios`: iPhone/iPad; only Safari's "Adicionar ao ecrã principal" installs.
 * - `other`: computers; installable from the address bar in Chrome/Edge.
 */
export type InstallPlatform = 'android' | 'ios' | 'other';

// The browser offers the install prompt once, early, so it is caught at boot
// (listenForInstallPrompt) and kept here until someone taps "Instalar".
let deferredPrompt: BeforeInstallPromptEvent | null = null;
let installedNow = false;
const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Keeps Chrome's install prompt for the "Instalar a app" button. Call once at boot.
 */
export function listenForInstallPrompt(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        // Show our own button instead of the browser's mini info bar.
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        notify();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        installedNow = true;
        notify();
    });
}

export function installPlatform(): InstallPlatform {
    if (typeof navigator === 'undefined') {
        return 'other';
    }

    if (
        /iPhone|iPad|iPod/.test(navigator.userAgent) ||
        // iPadOS reports itself as a Mac with touch.
        (navigator.userAgent.includes('Macintosh') &&
            navigator.maxTouchPoints > 1)
    ) {
        return 'ios';
    }

    return /Android/i.test(navigator.userAgent) ? 'android' : 'other';
}

/**
 * True when running as the installed app (opened from the home-screen icon).
 */
export function isInstalledApp(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia?.('(display-mode: standalone)').matches === true ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/**
 * Whether the app can be installed on this device, and how (FR-030, R11).
 */
export function useInstallPrompt() {
    const canPrompt = useSyncExternalStore(
        subscribe,
        () => deferredPrompt !== null,
        () => false,
    );
    const installed = useSyncExternalStore(
        subscribe,
        () => installedNow || isInstalledApp(),
        () => false,
    );

    const install = useCallback(async (): Promise<boolean> => {
        const event = deferredPrompt;

        if (!event) {
            return false;
        }

        // Both are taken before clearing: the build may read `event` from the
        // module variable again.
        const shown = event.prompt();
        const choice = event.userChoice;
        // A prompt can only be shown once; the browser sends a new one if needed.
        deferredPrompt = null;
        notify();
        await shown;
        const { outcome } = await choice;

        return outcome === 'accepted';
    }, []);

    return { platform: installPlatform(), installed, canPrompt, install };
}
