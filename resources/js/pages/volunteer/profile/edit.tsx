import { Head, Link } from '@inertiajs/react';
import { DataRemovalCard } from '@/components/app/data-removal-card';
import {
    VolunteerProfileForm,
    type ProfileFormOptions,
} from '@/components/volunteer-form/profile-form';
import { index as absences } from '@/routes/absences';
import { edit, update } from '@/routes/volunteer/profile';
import type { VolunteerFull } from '@/types';

type Props = ProfileFormOptions & {
    volunteer: VolunteerFull;
    canEditBirthDate: boolean;
};

/**
 * The volunteer's own data (FR-005). Birth date can only change while the registration
 * is under review.
 */
export default function VolunteerProfileEdit({
    volunteer,
    canEditBirthDate,
    ...options
}: Props) {
    return (
        <>
            <Head title="Os meus dados" />

            <VolunteerProfileForm
                volunteer={volunteer}
                canEditBirthDate={canEditBirthDate}
                submitUrl={update().url}
                {...options}
            />

            {(volunteer.status === 'active' ||
                volunteer.status === 'inactive') && (
                <p className="mt-10 text-sm text-muted-foreground">
                    Para bloquear datas ou fazer uma pausa, use o separador{' '}
                    <Link
                        href={absences()}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        Ausências
                    </Link>
                    .
                </p>
            )}

            <div className="mt-10">
                <DataRemovalCard scheduledFor={volunteer.removalScheduledFor} />
            </div>
        </>
    );
}

VolunteerProfileEdit.layout = {
    breadcrumbs: [{ title: 'Os meus dados', href: edit() }],
};
