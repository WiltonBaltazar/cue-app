/**
 * Display helpers. Dates are stored in UTC and always shown in Mozambique time,
 * in European Portuguese, whatever the device's own time zone is.
 */
export const DISPLAY_TIME_ZONE = 'Africa/Maputo';
const LOCALE = 'pt-PT';

type DateInput = Date | string | number;

function toDate(value: DateInput): Date {
    return value instanceof Date ? value : new Date(value);
}

function formatter(options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat(LOCALE, {
        timeZone: DISPLAY_TIME_ZONE,
        ...options,
    });
}

/** "02/11/2026" */
export function formatDate(value: DateInput): string {
    return formatter({
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(toDate(value));
}

/** "dom., 2 nov." — compact, for lists and notifications */
export function formatShortDate(value: DateInput): string {
    return formatter({
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(toDate(value));
}

/** "domingo, 2 de novembro de 2026" */
export function formatLongDate(value: DateInput): string {
    return formatter({
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(toDate(value));
}

/** "07:30" */
export function formatTime(value: DateInput): string {
    return formatter({
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(toDate(value));
}

/** "02/11/2026, 07:30" */
export function formatDateTime(value: DateInput): string {
    return `${formatDate(value)}, ${formatTime(value)}`;
}

/**
 * Mozambican numbers in E.164 ("+258841234567") shown as "+258 84 123 4567";
 * anything else is returned unchanged.
 */
export function formatPhone(e164: string): string {
    const match = /^\+258(\d{2})(\d{3})(\d{4})$/.exec(e164);

    return match ? `+258 ${match[1]} ${match[2]} ${match[3]}` : e164;
}
