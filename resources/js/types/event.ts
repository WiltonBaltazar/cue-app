/**
 * Event as serialised by App\Support\EventPresenter (times are local "H:i").
 */
export type EventSummary = {
    id: number;
    name: string;
    type: 'recurring' | 'special';
    date: string;
    arrivalTime: string;
    startTime: string;
    endTime: string;
    location: string;
    status: 'scheduled' | 'cancelled';
    statusLabel: string;
    isCustomized: boolean;
    requiredPeople: number;
};

export type EventPositionDetail = {
    teamRoleId: number;
    name: string;
    quantity: number;
    minLevel: string | null;
    minLevelLabel: string | null;
};

export type EventFull = EventSummary & {
    teardownMinutes: number;
    periodLabel: string;
    typeLabel: string;
    templateId: number | null;
    cancelReason: string | null;
    positions: EventPositionDetail[];
};
