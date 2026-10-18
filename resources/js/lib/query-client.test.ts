import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiFetch } from '@/lib/query-client';

function jsonResponse(status: number, body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('apiFetch', () => {
    const fetchMock = vi.fn<typeof fetch>();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=abc%3D123';
    });

    afterEach(() => {
        fetchMock.mockReset();
        vi.unstubAllGlobals();
    });

    it('sends the XSRF token and returns JSON', async () => {
        fetchMock.mockResolvedValue(jsonResponse(200, { count: 3 }));

        await expect(
            apiFetch<{ count: number }>('/app-api/notifications/unread-count'),
        ).resolves.toEqual({ count: 3 });

        const headers = new Headers(fetchMock.mock.calls[0][1]?.headers);
        expect(headers.get('X-XSRF-TOKEN')).toBe('abc=123');
        expect(fetchMock.mock.calls[0][1]?.credentials).toBe('same-origin');
    });

    it('throws ApiError with the server message on failure', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse(403, { message: 'Sem permissão' }),
        );

        await expect(apiFetch('/app-api/x')).rejects.toEqual(
            new ApiError(403, 'Sem permissão'),
        );
    });

    it('returns undefined for 204 responses', async () => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }));

        await expect(
            apiFetch('/app-api/notifications/1/read', { method: 'POST' }),
        ).resolves.toBeUndefined();
    });
});
