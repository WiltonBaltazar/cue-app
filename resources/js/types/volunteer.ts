/**
 * Volunteer as serialised by App\Support\VolunteerPresenter.
 */
export type VolunteerSummary = {
    id: number;
    fullName: string;
    status: string;
    statusLabel: string;
    inactiveUntil: string | null;
    isMinor: boolean;
    previouslyRejectedAt: string | null;
    submittedAt: string | null;
    roles: string[];
};

export type VolunteerSkillDetail = {
    teamRoleId: number;
    name: string;
    hasExperience: boolean;
    level: string;
    isPreferred: boolean;
};

/** Includes sensitive fields: only for coordinators and the volunteer themself. */
export type VolunteerFull = VolunteerSummary & {
    phone: string | null;
    email: string | null;
    birthDate: string;
    age: number;
    neighbourhood: string | null;
    guardianName: string | null;
    guardianPhone: string | null;
    guardianConsent: { confirmedAt: string; method: string | null } | null;
    educationLevel: string | null;
    training: string | null;
    commitments: string | null;
    motivations: string | null;
    comments: string | null;
    maxFrequency: string;
    maxFrequencyLabel: string;
    variableHours: boolean;
    availableForTraining: boolean;
    canSupportOthers: boolean;
    reviewNotes: string | null;
    reviewedAt: string | null;
    skills: VolunteerSkillDetail[];
    availability: { weekday: number; period: string; label: string }[];
};
