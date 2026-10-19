import { Link } from '@inertiajs/react';
import { ArrowLeftRight, CalendarDays, House, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import AppLogo from '@/components/app-logo';
import { NotificationBell } from '@/components/app/notification-bell';
import { InstallAppPrompt } from '@/components/app/install-app';
import { PushButton, PushPrompt } from '@/components/app/push-prompt';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { index as swaps } from '@/routes/swaps';
import { index as myShifts } from '@/routes/volunteer/shifts';
import type { NavItem } from '@/types';

/**
 * Volunteer navigation.
 */
const navItems: (NavItem & { matchPrefix?: string })[] = [
    { title: 'Início', href: home(), icon: House },
    {
        title: 'Escala',
        href: myShifts(),
        icon: CalendarDays,
        matchPrefix: '/escala',
    },
    { title: 'Trocas', href: swaps(), icon: ArrowLeftRight },
    {
        title: 'Perfil',
        href: editProfile(),
        icon: UserRound,
        matchPrefix: '/perfil',
    },
];

/**
 * Phone-first layout: content plus a bottom navigation bar with large touch
 * targets (≥ 44 px, FR-030/FR-041). On wider screens the same items move to
 * the top bar.
 */
export default function VolunteerLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();

    const isActive = (item: (typeof navItems)[number]) =>
        isCurrentUrl(item.href) ||
        (item.matchPrefix !== undefined &&
            isCurrentUrl(item.matchPrefix, undefined, true));

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="sticky top-0 z-20 border-b bg-background/95 backdrop-blur">
                <div className="mx-auto flex h-14 max-w-3xl items-center gap-2 px-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-2"
                        aria-label="Cue App RTVAO, início"
                    >
                        <AppLogo />
                    </Link>

                    <nav
                        aria-label="Navegação principal"
                        className="ml-6 hidden gap-1 md:flex"
                    >
                        {navItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                aria-current={
                                    isActive(item) ? 'page' : undefined
                                }
                                className={cn(
                                    'flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground',
                                    isActive(item) &&
                                        'bg-muted text-foreground',
                                )}
                            >
                                {item.icon && (
                                    <item.icon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {item.title}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-1">
                        <PushButton />
                        <NotificationBell />
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-3xl flex-1 px-4 pt-4 pb-24 md:pb-8">
                <InstallAppPrompt className="mb-4" />
                <PushPrompt className="mb-4" />
                {children}
            </main>

            <nav
                aria-label="Navegação principal"
                className="fixed inset-x-0 bottom-0 z-20 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden"
            >
                <ul className="mx-auto flex max-w-3xl">
                    {navItems.map((item) => (
                        <li key={item.title} className="flex-1">
                            <Link
                                href={item.href}
                                aria-current={
                                    isActive(item) ? 'page' : undefined
                                }
                                className={cn(
                                    'flex min-h-14 flex-col items-center justify-center gap-1 text-xs font-medium text-muted-foreground',
                                    isActive(item) && 'text-primary',
                                )}
                            >
                                {item.icon && (
                                    <item.icon
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                )}
                                {item.title}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}
