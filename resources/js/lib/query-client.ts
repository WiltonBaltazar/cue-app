import { QueryClient } from '@tanstack/react-query';
import { login } from '@/routes';

/**
 * Error thrown by apiFetch for non-2xx responses from /app-api (contracts/app-api.md).
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

type ApiBody = {
    message?: string;
    errors?: Record<string, string[]>;
};

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Same-origin JSON request using the session cookie (Sanctum SPA mode) and the
 * XSRF-TOKEN cookie Laravel sets. A 401 means the session ended: go to login.
 */
export async function apiFetch<T>(
    url: string,
    init: RequestInit = {},
): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (init.body !== undefined && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const token = xsrfToken();

    if (token) {
        headers.set('X-XSRF-TOKEN', token);
    }

    const response = await fetch(url, {
        ...init,
        headers,
        credentials: 'same-origin',
    });

    if (response.status === 401) {
        window.location.assign(login().url);

        throw new ApiError(401, 'Sessão terminada');
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const body = (await response.json().catch(() => ({}))) as ApiBody & T;

    if (!response.ok) {
        throw new ApiError(
            response.status,
            body.message ?? 'Ocorreu um erro. Tente novamente.',
            body.errors,
        );
    }

    return body;
}

export function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                refetchOnWindowFocus: true,
                // Never retry auth/permission/validation errors; retry flaky networks twice.
                retry: (failureCount, error) =>
                    !(
                        error instanceof ApiError &&
                        error.status >= 400 &&
                        error.status < 500
                    ) && failureCount < 2,
            },
        },
    });
}
