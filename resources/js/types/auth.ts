export type UserRole = 'coordinator' | 'volunteer';

/**
 * The signed-in user as shared by HandleInertiaRequests (own data only).
 */
export type User = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
    role: UserRole;
    volunteerStatus: string | null;
    twoFactorEnabled: boolean;
    avatar?: string;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
