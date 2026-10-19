import { useId } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { PhoneInput } from '@/components/phone-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type FieldProps = {
    label: string;
    hint?: ReactNode;
    error?: string;
    optional?: boolean;
    className?: string;
};

function describedBy(
    hintId: string,
    errorId: string,
    hint?: ReactNode,
    error?: string,
) {
    return (
        [hint ? hintId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined
    );
}

/**
 * Labelled input with hint and inline error next to the field (constitution III).
 */
export function TextField({
    label,
    hint,
    error,
    optional,
    className,
    ...inputProps
}: FieldProps & Omit<ComponentProps<typeof Input>, 'className'>) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {optional && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (opcional)
                    </span>
                )}
            </Label>
            <Input
                id={id}
                className="min-h-11"
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(hintId, errorId, hint, error)}
                {...inputProps}
            />
            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}

/**
 * TextField for phone numbers, with the fixed "+258" prefix.
 */
export function PhoneField({
    label,
    hint,
    error,
    optional,
    className,
    ...inputProps
}: FieldProps & Omit<ComponentProps<typeof PhoneInput>, 'className'>) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {optional && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (opcional)
                    </span>
                )}
            </Label>
            <PhoneInput
                id={id}
                className="min-h-11"
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(hintId, errorId, hint, error)}
                {...inputProps}
            />
            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}

export function TextAreaField({
    label,
    hint,
    error,
    optional,
    className,
    ...textareaProps
}: FieldProps & Omit<ComponentProps<typeof Textarea>, 'className'>) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {optional && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (opcional)
                    </span>
                )}
            </Label>
            <Textarea
                id={id}
                rows={4}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(hintId, errorId, hint, error)}
                {...textareaProps}
            />
            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}

type CheckboxFieldProps = {
    label: ReactNode;
    checked: boolean;
    onChange: (checked: boolean) => void;
    hint?: ReactNode;
    error?: string;
};

/**
 * Native checkbox with a 44 px touch area (FR-041).
 */
export function CheckboxField({
    label,
    checked,
    onChange,
    hint,
    error,
}: CheckboxFieldProps) {
    const id = useId();

    return (
        <div className="grid gap-1">
            <label
                htmlFor={id}
                className="flex min-h-11 cursor-pointer items-start gap-3 rounded-md py-2"
            >
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    aria-invalid={error ? true : undefined}
                    className="mt-0.5 size-5 shrink-0 accent-primary"
                />
                <span className="text-sm leading-6">{label}</span>
            </label>
            {hint && (
                <p className="pl-8 text-sm text-muted-foreground">{hint}</p>
            )}
            <InputError className="pl-8" message={error} />
        </div>
    );
}

type Option<T extends string> = { value: T; label: string };

type RadioGroupFieldProps<T extends string> = {
    legend: string;
    name: string;
    options: Option<T>[];
    value: T | '';
    onChange: (value: T) => void;
    error?: string;
    hint?: ReactNode;
};

export function RadioGroupField<T extends string>({
    legend,
    name,
    options,
    value,
    onChange,
    error,
    hint,
}: RadioGroupFieldProps<T>) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">{legend}</legend>
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 text-sm',
                            value === option.value && 'border-primary bg-muted',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="size-4 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
