import { Head } from '@inertiajs/react';
import {
    VolunteerProfileForm,
    type ProfileFormOptions,
} from '@/components/volunteer-form/profile-form';
import { index } from '@/routes/coord/volunteers';
import { update } from '@/routes/coord/volunteers/profile';
import type { VolunteerFull } from '@/types';

type Props = ProfileFormOptions & {
    volunteer: VolunteerFull;
};

/**
 * A coordinator correcting a volunteer's data (FR-005). Same form the volunteer sees;
 * coordinators can also change the name and birth date.
 */
export default function VolunteerProfile({ volunteer, ...options }: Props) {
    return (
        <>
            <Head title={`Dados de ${volunteer.fullName}`} />

            <div className="space-y-8 p-4 lg:max-w-3xl">
                <h1 className="text-2xl font-semibold">
                    Dados de {volunteer.fullName}
                </h1>

                <VolunteerProfileForm
                    volunteer={volunteer}
                    canEditBirthDate
                    submitUrl={update(volunteer.id).url}
                    {...options}
                />
            </div>
        </>
    );
}

VolunteerProfile.layout = {
    breadcrumbs: [{ title: 'Voluntários', href: index() }],
};
