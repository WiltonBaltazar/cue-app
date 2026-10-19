export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type TeamRoleOption = {
    id: number;
    name: string;
    description: string | null;
};

export type SkillInput = {
    team_role_id: number;
    has_experience: boolean;
};

export type AvailabilitySlot = {
    weekday: number;
    period: string;
};

/**
 * Fields shared by the registration form and the volunteer's own profile page.
 */
export type VolunteerFormFields = {
    neighbourhood: string;
    guardian_name: string;
    guardian_phone: string;
    education_level: string;
    training: string;
    skills: SkillInput[];
    preferred_role_id: number | null;
    can_support_others: boolean;
    availability: AvailabilitySlot[];
    max_frequency: string;
    variable_hours: boolean;
    available_for_training: boolean;
    commitments: string;
    motivations: string;
    comments: string;
};

export const MINIMUM_AGE = 14;
export const ADULT_AGE = 18;

/**
 * Age in full years on today's date (the server re-checks in Africa/Maputo time).
 */
export function ageOn(
    birthDate: string,
    today: Date = new Date(),
): number | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(birthDate);

    if (!match) {
        return null;
    }

    const [year, month, day] = [
        Number(match[1]),
        Number(match[2]),
        Number(match[3]),
    ];
    let age = today.getFullYear() - year;
    const beforeBirthday =
        today.getMonth() + 1 < month ||
        (today.getMonth() + 1 === month && today.getDate() < day);

    if (beforeBirthday) {
        age -= 1;
    }

    return age;
}
