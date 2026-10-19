import { Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as sessions } from '@/routes/sessions';
import { edit as volunteerData } from '@/routes/volunteer/profile';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    { title: 'Conta', href: edit(), icon: null },
    { title: 'Segurança', href: editSecurity(), icon: null },
    { title: 'Sessões', href: sessions(), icon: null },
    { title: 'Aparência', href: editAppearance(), icon: null },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const items: NavItem[] = auth.user?.volunteerStatus
        ? [
              { title: 'Os meus dados', href: volunteerData(), icon: null },
              ...sidebarNavItems,
          ]
        : sidebarNavItems;

    return (
        <div className="py-6 md:px-4">
            <Heading
                title="Perfil"
                description="Gerir a sua conta, a segurança e as sessões"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Perfil"
                    >
                        {items.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                variant="ghost"
                                asChild
                                className={cn('min-h-11 w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link
                                    href={item.href}
                                    aria-current={
                                        isCurrentOrParentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                        <Button
                            variant="ghost"
                            asChild
                            className="min-h-11 w-full justify-start text-red-700 hover:text-red-800 dark:text-red-400"
                        >
                            <Link
                                href={logout()}
                                as="button"
                                onClick={() => router.flushAll()}
                                data-test="logout-button"
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                Sair
                            </Link>
                        </Button>
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
