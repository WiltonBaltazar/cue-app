import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import type { AppNotificationItem } from '@/hooks/use-notifications';
import { useNotifications } from '@/hooks/use-notifications';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

export function NotificationBell({ className }: { className?: string }) {
    const [open, setOpen] = useState(false);
    const { unreadCount, notifications, isLoading, isError, markRead } =
        useNotifications({ listEnabled: open });

    const label =
        unreadCount > 0
            ? `Notificações, ${unreadCount} por ler`
            : 'Notificações';

    const openNotification = (item: AppNotificationItem) => {
        if (item.readAt === null) {
            markRead(item.id);
        }

        router.visit(item.url);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('relative size-11', className)}
                    aria-label={label}
                >
                    <Bell className="size-5" aria-hidden="true" />
                    {unreadCount > 0 && (
                        <span
                            aria-hidden="true"
                            className="absolute top-1.5 right-1.5 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-semibold text-white"
                        >
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 max-w-[90vw]">
                <DropdownMenuLabel>Notificações</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {isLoading && (
                    <div className="flex justify-center p-4">
                        <Spinner />
                    </div>
                )}
                {isError && (
                    <p className="p-4 text-sm text-muted-foreground">
                        Não foi possível carregar as notificações.
                    </p>
                )}
                {!isLoading && !isError && notifications.length === 0 && (
                    <p className="p-4 text-sm text-muted-foreground">
                        Não tem notificações.
                    </p>
                )}
                {notifications.map((item) => (
                    <DropdownMenuItem
                        key={item.id}
                        onSelect={() => openNotification(item)}
                        className="flex flex-col items-start gap-0.5 py-2"
                    >
                        <span
                            className={cn(
                                'text-sm',
                                item.readAt === null && 'font-semibold',
                            )}
                        >
                            {item.title}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {item.body}
                        </span>
                        {item.createdAt && (
                            <span className="text-xs text-muted-foreground">
                                {formatDateTime(item.createdAt)}
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
