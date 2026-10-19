import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

/**
 * Simple layout for the public registration form and for volunteers who cannot use the
 * app yet (registration under review, declaration to accept).
 */
export default function MinimalLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="border-b">
                <div className="mx-auto flex h-14 max-w-2xl items-center px-4">
                    <div className="flex items-center gap-2">
                        <AppLogo />
                    </div>
                    {auth.user && (
                        <Button
                            variant="ghost"
                            asChild
                            className="ml-auto min-h-11"
                        >
                            <Link
                                href={logout()}
                                as="button"
                                onClick={() => router.flushAll()}
                                data-test="logout-button"
                            >
                                Sair
                            </Link>
                        </Button>
                    )}
                </div>
            </header>
            <main className="mx-auto w-full max-w-2xl flex-1 px-4 py-6">
                {children}
            </main>
        </div>
    );
}
