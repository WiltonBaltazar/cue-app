import {
    ClipboardList,
    FileText,
    LayoutGrid,
    Settings,
    Users,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { dashboard } from '@/routes/coord';
import { edit as consent } from '@/routes/coord/consent';
import { index as registrations } from '@/routes/coord/registrations';
import { edit as settings } from '@/routes/coord/settings';
import { index as volunteers } from '@/routes/coord/volunteers';
import type { BreadcrumbItem, NavItem } from '@/types';

/**
 * Coordinator navigation; each user story adds its section (registrations,
 * events, schedules, swaps, overview) as it lands.
 */
const navItems: NavItem[] = [
    { title: 'Painel', href: dashboard(), icon: LayoutGrid },
    { title: 'Cadastros', href: registrations(), icon: ClipboardList },
    { title: 'Voluntários', href: volunteers(), icon: Users },
    { title: 'Declaração', href: consent(), icon: FileText },
    { title: 'Definições', href: settings(), icon: Settings },
];

export default function CoordinatorLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar items={navItems} homeHref={dashboard()} />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
