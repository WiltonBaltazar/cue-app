import {
    ArrowLeftRight,
    BriefcaseBusiness,
    CalendarDays,
    CalendarRange,
    ChartColumn,
    ClipboardList,
    FileText,
    Grid3x3,
    LayoutGrid,
    Repeat,
    Settings,
    Users,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PushPrompt } from '@/components/app/push-prompt';
import { availability, coverage, dashboard } from '@/routes/coord';
import { edit as consent } from '@/routes/coord/consent';
import { index as events } from '@/routes/coord/events';
import { index as registrations } from '@/routes/coord/registrations';
import { index as schedules } from '@/routes/coord/schedules';
import { edit as settings } from '@/routes/coord/settings';
import { index as swaps } from '@/routes/coord/swaps';
import { index as teamRoles } from '@/routes/coord/team-roles';
import { index as templates } from '@/routes/coord/templates';
import { index as volunteers } from '@/routes/coord/volunteers';
import type { BreadcrumbItem, NavItem } from '@/types';

/**
 * Coordinator navigation; each user story adds its section (registrations,
 * events, schedules, swaps, overview) as it lands.
 */
const navItems: NavItem[] = [
    { title: 'Painel', href: dashboard(), icon: LayoutGrid },
    { title: 'Escalas', href: schedules(), icon: CalendarRange },
    { title: 'Trocas', href: swaps(), icon: ArrowLeftRight },
    { title: 'Cobertura', href: coverage(), icon: ChartColumn },
    { title: 'Disponibilidade', href: availability(), icon: Grid3x3 },
    { title: 'Eventos', href: events(), icon: CalendarDays },
    { title: 'Actividades', href: templates(), icon: Repeat },
    { title: 'Cadastros', href: registrations(), icon: ClipboardList },
    { title: 'Voluntários', href: volunteers(), icon: Users },
    { title: 'Funções', href: teamRoles(), icon: BriefcaseBusiness },
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
                <PushPrompt className="m-4 mb-0" />
                {children}
            </AppContent>
        </AppShell>
    );
}
