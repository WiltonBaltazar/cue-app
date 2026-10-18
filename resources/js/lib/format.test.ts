import { describe, expect, it } from 'vitest';
import {
    formatDate,
    formatDateTime,
    formatLongDate,
    formatPhone,
    formatTime,
} from '@/lib/format';

// 2026-11-02 05:30 UTC is 07:30 in Maputo (UTC+2, no DST).
const MONDAY_MORNING_UTC = '2026-11-02T05:30:00Z';

describe('format', () => {
    it('shows times in Africa/Maputo regardless of the device time zone', () => {
        expect(formatTime(MONDAY_MORNING_UTC)).toBe('07:30');
        expect(formatDateTime(MONDAY_MORNING_UTC)).toBe('02/11/2026, 07:30');
    });

    it('moves late-night UTC times to the next local day', () => {
        expect(formatDate('2026-11-01T23:30:00Z')).toBe('02/11/2026');
    });

    it('writes long dates in European Portuguese', () => {
        expect(formatLongDate(MONDAY_MORNING_UTC)).toBe(
            'segunda-feira, 2 de novembro de 2026',
        );
    });

    it('groups Mozambican phone numbers and leaves others alone', () => {
        expect(formatPhone('+258841234567')).toBe('+258 84 123 4567');
        expect(formatPhone('+351912345678')).toBe('+351912345678');
    });
});
