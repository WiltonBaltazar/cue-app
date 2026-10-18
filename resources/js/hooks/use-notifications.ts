import { usePage } from '@inertiajs/react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/query-client';
import {
    index as notificationsIndex,
    read as markReadRoute,
    unreadCount as unreadCountRoute,
} from '@/routes/app-api/notifications';

export type AppNotificationItem = {
    id: string;
    type: string | null;
    title: string;
    body: string;
    url: string;
    readAt: string | null;
    createdAt: string | null;
};

type NotificationPage = {
    data: AppNotificationItem[];
    nextPage: number | null;
};

const keys = {
    unread: ['notifications', 'unread-count'] as const,
    list: ['notifications', 'list'] as const,
};

/**
 * In-app notification centre (FR-040). The unread count starts from the shared
 * Inertia prop and is refreshed every minute; the list loads when it is opened.
 */
export function useNotifications({ listEnabled }: { listEnabled: boolean }) {
    const { unreadNotifications } = usePage().props;
    const queryClient = useQueryClient();

    const unread = useQuery({
        queryKey: keys.unread,
        queryFn: () =>
            apiFetch<{ count: number }>(unreadCountRoute().url).then(
                (body) => body.count,
            ),
        initialData: unreadNotifications,
        refetchInterval: 60_000,
    });

    const list = useQuery({
        queryKey: keys.list,
        queryFn: () => apiFetch<NotificationPage>(notificationsIndex().url),
        enabled: listEnabled,
    });

    const markRead = useMutation({
        mutationFn: (id: string) =>
            apiFetch<void>(markReadRoute(id).url, { method: 'POST' }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['notifications'] });
        },
    });

    return {
        unreadCount: unread.data,
        notifications: list.data?.data ?? [],
        isLoading: list.isPending && listEnabled,
        isError: list.isError,
        markRead: markRead.mutate,
    };
}
