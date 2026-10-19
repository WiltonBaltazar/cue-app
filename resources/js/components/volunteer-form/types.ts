export type Option<T extends string | number = string> = {
    value: T;
    label: string;
    hint?: string | null;
};

export type TeamRoleOption = {
    id: number;
    name: string;
    description: string | null;
};

export type SkillInput = {
    team_role_id: number;
};

export type SocialMediaInput = {
    network: string;
    handle: string;
};

export type AvailabilitySlot = {
    weekday: number;
    period: string;
};

/**
 * Fields shared by the registration form and the volunteer's own profile page.
 */
export type VolunteerFormFields = {
    nickname: string;
    neighbourhood: string;
    phone_has_whatsapp: boolean;
    whatsapp_phone: string;
    social_media: SocialMediaInput[];
    guardian_name: string;
    guardian_phone: string;
    education_level: string;
    training: string;
    skills: SkillInput[];
    preferred_role_id: number | null;
    other_area: string;
    experience_role_ids: number[];
    other_experience: string;
    no_experience: boolean;
    experience_description: string;
    can_support_others: boolean;
    availability: AvailabilitySlot[];
    other_days_by_arrangement: boolean;
    max_frequency: string;
    frequency_note: string;
    variable_hours: boolean;
    training_availability: string;
    commitments: string;
    motivations: string;
    expectations: string;
    goals: string;
    comments: string;
    suggestions: string;
};

/** Answers of question 5 on the paper form; anything else is "Outro". */
export const EDUCATION_LEVELS = [
    'Ensino primário',
    'Ensino secundário',
    'Ensino técnico-profissional',
    'Ensino superior',
];

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
