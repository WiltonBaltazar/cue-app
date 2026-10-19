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

/** "domingo, 2 de novembro" */
export function formatDayMonth(value: DateInput): string {
    return formatter({
        weekday: 'long',
        day: 'numeric',
        month: 'long',
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

/** Today in Mozambique as "YYYY-MM-DD". */
function todayInDisplayZone(now: Date = new Date()): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: DISPLAY_TIME_ZONE,
    }).format(now);
}

/** Whole days from today (Mozambique) to a "YYYY-MM-DD" date; negative if past. */
export function daysUntil(date: string, now: Date = new Date()): number {
    const toUtcDay = (value: string) => Date.parse(`${value}T00:00:00Z`);

    return Math.round(
        (toUtcDay(date) - toUtcDay(todayInDisplayZone(now))) / 86_400_000,
    );
}

/** "Hoje", "Amanhã", "Daqui a 5 dias" for a "YYYY-MM-DD" date. */
export function relativeDay(date: string, now: Date = new Date()): string {
    const days = daysUntil(date, now);

    if (days === 0) {
        return 'Hoje';
    }

    if (days === 1) {
        return 'Amanhã';
    }

    return days > 1
        ? `Daqui a ${days} dias`
        : formatShortDate(`${date}T12:00:00Z`);
}

/** Parts of a "YYYY-MM-DD" date for a calendar-style block: "dom.", "22", "nov.". */
export function dateParts(date: string): {
    weekday: string;
    day: string;
    month: string;
} {
    const value = `${date}T12:00:00Z`;

    // pt-PT has no short weekday ("domingo"); the first three letters are the usual one.
    return {
        weekday: formatter({ weekday: 'long' })
            .format(toDate(value))
            .slice(0, 3),
        day: formatter({ day: 'numeric' }).format(toDate(value)),
        month: formatter({ month: 'short' })
            .format(toDate(value))
            .replace('.', ''),
    };
}

/** "novembro de 2026" for a "YYYY-MM-DD" date. */
export function formatMonth(date: string): string {
    return formatter({ month: 'long', year: 'numeric' }).format(
        toDate(`${date}T12:00:00Z`),
    );
}
