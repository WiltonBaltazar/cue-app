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
    nickname: string | null;
    neighbourhood: string | null;
    phoneHasWhatsapp: boolean;
    whatsappPhone: string | null;
    socialMedia: { network: string; handle: string }[];
    guardianName: string | null;
    guardianPhone: string | null;
    guardianConsent: { confirmedAt: string; method: string | null } | null;
    educationLevel: string | null;
    training: string | null;
    otherArea: string | null;
    experienceRoleIds: number[];
    experienceAreas: string[];
    otherExperience: string | null;
    experienceDescription: string | null;
    commitments: string | null;
    motivations: string | null;
    expectations: string | null;
    goals: string | null;
    comments: string | null;
    suggestions: string | null;
    maxFrequency: string;
    maxFrequencyLabel: string;
    frequencyNote: string | null;
    variableHours: boolean;
    otherDaysByArrangement: boolean;
    trainingAvailability: string;
    trainingAvailabilityLabel: string;
    canSupportOthers: boolean;
    reviewNotes: string | null;
    reviewedAt: string | null;
    removalScheduledFor: string | null;
    skills: VolunteerSkillDetail[];
    availability: { weekday: number; period: string; label: string }[];
};
