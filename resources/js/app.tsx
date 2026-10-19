import { createInertiaApp } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { listenForInstallPrompt } from '@/hooks/use-install-prompt';
import { registerServiceWorker } from '@/hooks/use-push-subscription';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import MinimalLayout from '@/layouts/minimal-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { createQueryClient } from '@/lib/query-client';

const appName = import.meta.env.VITE_APP_NAME || 'Cue App';
const queryClient = createQueryClient();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('errors/'):
                return null;
            case name.startsWith('registration/'):
            case name === 'volunteer/status':
            case name === 'volunteer/consent':
                return MinimalLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('volunteer/profile/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <QueryClientProvider client={queryClient}>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Installable app, offline page and push (R11).
registerServiceWorker();
listenForInstallPrompt();
