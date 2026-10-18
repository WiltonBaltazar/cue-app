import { usePage } from '@inertiajs/react';
import CoordinatorLayout from '@/layouts/coordinator-layout';
import VolunteerLayout from '@/layouts/volunteer-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Default layout for signed-in pages: coordinators get the sidebar, volunteers
 * the phone-first bottom navigation.
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;

    if (auth.user?.role === 'coordinator') {
        return (
            <CoordinatorLayout breadcrumbs={breadcrumbs}>
                {children}
            </CoordinatorLayout>
        );
    }

    return <VolunteerLayout>{children}</VolunteerLayout>;
}
