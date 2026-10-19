import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/query-client';
import {
    destroy as destroySubscription,
    store as storeSubscription,
} from '@/routes/app-api/push-subscriptions';

/**
 * - `unsupported`: no service worker or push in this browser (or a dev build).
 * - `needs-install`: iPhone/iPad in Safari; push only works from the home-screen app.
 * - `denied`: the user blocked notifications in the browser settings.
 * - `available`: can ask for permission and subscribe.
 * - `subscribed`: this device receives push.
 */
export type PushState =
    | 'loading'
    | 'unsupported'
    | 'needs-install'
    | 'denied'
    | 'available'
    | 'subscribed';

function isIos(): boolean {
    return (
        /iPhone|iPad|iPod/.test(navigator.userAgent) ||
        // iPadOS reports itself as a Mac with touch.
        (navigator.userAgent.includes('Macintosh') &&
            navigator.maxTouchPoints > 1)
    );
}

function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function applicationServerKey(base64Url: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const raw = atob(
        (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/'),
    );
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

async function saveOnServer(subscription: PushSubscription): Promise<void> {
    const encodings = (
        PushManager as unknown as { supportedContentEncodings?: string[] }
    ).supportedContentEncodings;

    await apiFetch(storeSubscription().url, {
        method: 'POST',
        body: JSON.stringify({
            ...subscription.toJSON(),
            contentEncoding: encodings?.includes('aes128gcm')
                ? 'aes128gcm'
                : 'aesgcm',
        }),
    });
}

/**
 * The active worker; development builds register none, so there is nothing to wait for.
 */
async function currentRegistration(): Promise<ServiceWorkerRegistration | null> {
    return import.meta.env.PROD ? navigator.serviceWorker.ready : null;
}

/**
 * This device's Web Push subscription (R12). An existing subscription is sent
 * to the server again on load so it always belongs to whoever is signed in.
 */
export function usePushSubscription() {
    const { vapidPublicKey } = usePage().props;
    const [state, setState] = useState<PushState>('loading');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const detect = async (): Promise<PushState> => {
            if (!('serviceWorker' in navigator)) {
                return 'unsupported';
            }

            if (isIos() && !isStandalone()) {
                return 'needs-install';
            }

            const registration = await currentRegistration();

            if (
                !vapidPublicKey ||
                registration === null ||
                !('PushManager' in window) ||
                !('Notification' in window)
            ) {
                return 'unsupported';
            }

            if (Notification.permission === 'denied') {
                return 'denied';
            }

            const subscription =
                await registration.pushManager.getSubscription();

            if (
                subscription !== null &&
                Notification.permission === 'granted'
            ) {
                await saveOnServer(subscription).catch(() => undefined);

                return 'subscribed';
            }

            return 'available';
        };

        void detect().then((next) => {
            if (!cancelled) {
                setState(next);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [vapidPublicKey]);

    const subscribe = useCallback(async (): Promise<boolean> => {
        const registration = await currentRegistration();

        if (!vapidPublicKey || registration === null) {
            return false;
        }

        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setState(permission === 'denied' ? 'denied' : 'available');

                return false;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey(vapidPublicKey),
            });

            await saveOnServer(subscription);
            setState('subscribed');

            return true;
        } catch {
            return false;
        } finally {
            setBusy(false);
        }
    }, [vapidPublicKey]);

    const unsubscribe = useCallback(async (): Promise<void> => {
        const subscription = await (
            await currentRegistration()
        )?.pushManager.getSubscription();

        setBusy(true);

        try {
            if (subscription) {
                await apiFetch(destroySubscription().url, {
                    method: 'DELETE',
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });
                await subscription.unsubscribe();
            }

            setState('available');
        } finally {
            setBusy(false);
        }
    }, []);

    return { state, busy, subscribe, unsubscribe };
}

/**
 * Registers the service worker in production builds; development has none.
 */
export function registerServiceWorker(): void {
    if (import.meta.env.PROD && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            void navigator.serviceWorker
                .register('/sw.js', { scope: '/' })
                .catch(() => undefined);
        });
    }
}
