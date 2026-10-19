import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export const PHONE_PREFIX = '+258';

/**
 * Phone input with a fixed "+258" prefix: people type only the local number
 * and the server normalises it to E.164 with the MZ region (App\Support\Phone).
 */
export function PhoneInput({
    className,
    ...props
}: Omit<ComponentProps<typeof Input>, 'type' | 'inputMode'>) {
    return (
        <div className="relative">
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 left-0 flex items-center border-r border-input pr-2.5 pl-3 text-base text-muted-foreground md:text-sm"
            >
                {PHONE_PREFIX}
            </span>
            <Input
                type="tel"
                inputMode="tel"
                className={cn('pl-17', className)}
                {...props}
            />
        </div>
    );
}

/**
 * The local part of a Mozambican number, for prefilling a PhoneInput;
 * other numbers are returned unchanged.
 */
export function localPhone(e164: string): string {
    return e164.startsWith(PHONE_PREFIX)
        ? e164.slice(PHONE_PREFIX.length)
        : e164;
}
