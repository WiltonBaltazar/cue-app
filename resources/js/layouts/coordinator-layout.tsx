import { LayoutGrid } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { dashboard } from '@/routes/coord';
import type { BreadcrumbItem, NavItem } from '@/types';

/**
 * Coordinator navigation; each user story adds its section (registrations,
 * events, schedules, swaps, overview) as it lands.
 */
const navItems: NavItem[] = [
    { title: 'Painel', href: dashboard(), icon: LayoutGrid },
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
