/// <reference lib="webworker" />

import {
    cleanupOutdatedCaches,
    matchPrecache,
    precacheAndRoute,
} from 'workbox-precaching';
import type { PrecacheEntry } from 'workbox-precaching';
import {
    NavigationRoute,
    registerRoute,
    setCatchHandler,
} from 'workbox-routing';
import { NetworkFirst, NetworkOnly } from 'workbox-strategies';

/**
 * Cue App service worker (research R11, contracts/notifications.md).
 *
 * - Precaches the built app shell and the "Sem ligação" page.
 * - Keeps the last full load of /minha-escala as a read-only offline copy;
 *   no other page and no write is ever served from cache.
 * - Shows pushes and opens their in-app path when tapped.
 */
declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: (PrecacheEntry | string)[];
};

const OFFLINE_PAGE = '/offline.html';
const OWN_SHIFTS_CACHE = 'cue-own-shifts';
const OWN_SHIFTS_PATH = '/minha-escala';
const LOGOUT_PATH = '/sair';

type PushPayload = {
    title: string;
    body?: string;
    url?: string;
    tag?: string;
    type?: string;
    actions?: { action: string; title: string; url: string }[];
};

type NotificationData = {
    url: string;
    actions: Record<string, string>;
};

// The PWA plugin lists the web manifest relative to the worker ("/"), but it is
// served from /build/. Left as is, it 404s and the worker never installs, which
// means no push and no offline page.
precacheAndRoute(
    self.__WB_MANIFEST.map((entry) =>
        typeof entry !== 'string' && entry.url === 'manifest.webmanifest'
            ? { ...entry, url: '/build/manifest.webmanifest' }
            : entry,
    ),
);
cleanupOutdatedCaches();

self.addEventListener('install', () => {
    void self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Own shifts: network first, falling back to the last successful full load.
registerRoute(
    ({ request, url }) =>
        request.mode === 'navigate' && url.pathname === OWN_SHIFTS_PATH,
    new NetworkFirst({
        cacheName: OWN_SHIFTS_CACHE,
        networkTimeoutSeconds: 5,
        plugins: [
            {
                // One copy, whatever the query string (e.g. ?recusar=).
                cacheKeyWillBeUsed: async () =>
                    new URL(OWN_SHIFTS_PATH, self.location.origin).href,
                // Never keep a login redirect or an error page.
                cacheWillUpdate: async ({ response }) =>
                    response.status === 200 && !response.redirected
                        ? response
                        : null,
            },
        ],
    }),
);

// Signing out forgets the offline copy, so the next person on this device never sees it.
registerRoute(
    ({ url }) => url.pathname === LOGOUT_PATH,
    async ({ request }) => {
        await caches.delete(OWN_SHIFTS_CACHE);

        return fetch(request);
    },
    'POST',
);

// Every other page always comes from the network; offline, the catch handler answers.
registerRoute(new NavigationRoute(new NetworkOnly()));

setCatchHandler(async ({ request }) => {
    if (request.destination === 'document') {
        return (await matchPrecache(OFFLINE_PAGE)) ?? Response.error();
    }

    return Response.error();
});

self.addEventListener('push', (event) => {
    const payload = readPayload(event.data);

    if (payload === null) {
        return;
    }

    const data: NotificationData = {
        url: payload.url ?? '/',
        actions: Object.fromEntries(
            (payload.actions ?? []).map((action) => [
                action.action,
                action.url,
            ]),
        ),
    };

    // `actions` is supported by Chromium; other browsers ignore it and use `url`.
    const options: NotificationOptions & {
        actions?: { action: string; title: string }[];
    } = {
        body: payload.body,
        tag: payload.tag,
        lang: 'pt',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        data,
        actions: payload.actions?.map(({ action, title }) => ({
            action,
            title,
        })),
    };

    event.waitUntil(self.registration.showNotification(payload.title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data as NotificationData | null;
    const path =
        (event.action && data?.actions[event.action]) || data?.url || '/';
    const target = new URL(path, self.location.origin);

    // Only ever open pages of this app.
    if (target.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(openInApp(target.href));
});

function readPayload(data: PushMessageData | null): PushPayload | null {
    try {
        const payload = data?.json() as PushPayload | undefined;

        return payload && typeof payload.title === 'string' ? payload : null;
    } catch {
        return null;
    }
}

async function openInApp(href: string): Promise<void> {
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    for (const client of windows) {
        if (new URL(client.url).origin !== self.location.origin) {
            continue;
        }

        try {
            const focused = await client.focus();
            await focused.navigate(href);

            return;
        } catch {
            // Not controlled by this worker yet: open a new window instead.
        }
    }

    await self.clients.openWindow(href);
}
