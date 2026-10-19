import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { EDUCATION_LEVELS } from './types';

type Props = {
    value: string;
    onChange: (value: string) => void;
    error?: string;
};

const OTHER = 'outro';

/**
 * Highest completed education (question 5): the paper form's options plus "Outro"
 * with free text. The answer is stored as its label.
 */
export function EducationField({ value, onChange, error }: Props) {
    const isListed = EDUCATION_LEVELS.includes(value);
    const [otherChosen, setOtherChosen] = useState(value !== '' && !isListed);
    const selected = isListed ? value : otherChosen ? OTHER : '';

    const options = [
        ...EDUCATION_LEVELS.map((level) => ({ value: level, label: level })),
        { value: OTHER, label: 'Outro' },
    ];

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Nível de escolaridade concluído
                <span className="font-normal text-muted-foreground">
                    {' '}
                    (opcional)
                </span>
            </legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 text-sm',
                            selected === option.value &&
                                'border-primary bg-muted',
                        )}
                    >
                        <input
                            type="radio"
                            name="education_level"
                            checked={selected === option.value}
                            onChange={() => {
                                const other = option.value === OTHER;
                                setOtherChosen(other);
                                onChange(
                                    other
                                        ? isListed
                                            ? ''
                                            : value
                                        : option.value,
                                );
                            }}
                            className="size-4 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
            {selected === OTHER && (
                <Input
                    aria-label="Outro nível de escolaridade"
                    placeholder="Qual?"
                    className="min-h-11"
                    maxLength={120}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
            <InputError message={error} />
        </fieldset>
    );
}
