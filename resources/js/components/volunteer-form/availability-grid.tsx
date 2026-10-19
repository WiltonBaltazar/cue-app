import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import type { AvailabilitySlot, Option } from './types';

type Props = {
    weekdays: Option<number>[];
    periods: Option[];
    value: AvailabilitySlot[];
    onChange: (slots: AvailabilitySlot[]) => void;
    error?: string;
};

/**
 * Weekday × period grid of checkboxes (form section V). On phones it stacks one
 * day per row so every target stays ≥ 44 px (FR-030/FR-041).
 */
export function AvailabilityGrid({
    weekdays,
    periods,
    value,
    onChange,
    error,
}: Props) {
    const isChecked = (weekday: number, period: string) =>
        value.some(
            (slot) => slot.weekday === weekday && slot.period === period,
        );

    const toggle = (weekday: number, period: string, checked: boolean) =>
        onChange(
            checked
                ? [...value, { weekday, period }]
                : value.filter(
                      (slot) =>
                          !(slot.weekday === weekday && slot.period === period),
                  ),
        );

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Quando está normalmente disponível?
            </legend>
            <p className="text-sm text-muted-foreground">
                Marque todos os dias e períodos em que pode servir.
            </p>

            <div className="grid gap-2">
                {weekdays.map((day) => (
                    <div
                        key={day.value}
                        className="grid grid-cols-[6rem_1fr] items-center gap-2"
                    >
                        <span className="text-sm font-medium">{day.label}</span>
                        <div className="grid grid-cols-3 gap-2">
                            {periods.map((period) => {
                                const checked = isChecked(
                                    day.value,
                                    period.value,
                                );

                                return (
                                    <label
                                        key={period.value}
                                        className={cn(
                                            'flex min-h-11 cursor-pointer items-center justify-center rounded-md border px-1 text-sm has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-offset-2',
                                            checked &&
                                                'border-primary bg-primary text-primary-foreground',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={checked}
                                            aria-label={`${day.label}, ${period.label}`}
                                            onChange={(event) =>
                                                toggle(
                                                    day.value,
                                                    period.value,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        <span aria-hidden="true">
                                            {period.label}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
