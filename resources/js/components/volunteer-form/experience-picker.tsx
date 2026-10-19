import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { TeamRoleOption } from './types';

type Props = {
    roles: TeamRoleOption[];
    value: number[];
    onChange: (roleIds: number[]) => void;
    other: string;
    onOtherChange: (other: string) => void;
    noExperience: boolean;
    onNoExperienceChange: (noExperience: boolean) => void;
    error?: string;
};

/**
 * Areas where the person has experience, independent of where they want to serve
 * (form section IV, question 8). "No experience yet" clears the rest.
 */
export function ExperiencePicker({
    roles,
    value,
    onChange,
    other,
    onOtherChange,
    noExperience,
    onNoExperienceChange,
    error,
}: Props) {
    const toggle = (roleId: number, checked: boolean) => {
        onChange(
            checked
                ? [...value, roleId]
                : value.filter((selected) => selected !== roleId),
        );

        if (checked) {
            onNoExperienceChange(false);
        }
    };

    const setNoExperience = (checked: boolean) => {
        onNoExperienceChange(checked);

        if (checked) {
            onChange([]);
            onOtherChange('');
        }
    };

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Em que áreas tem experiência ou conhecimentos práticos?
            </legend>
            <p className="text-sm text-muted-foreground">
                Pode ser numa área diferente daquelas em que quer servir.
            </p>

            <ul className="grid gap-2 sm:grid-cols-2">
                {roles.map((role) => {
                    const checked = value.includes(role.id);

                    return (
                        <li key={role.id}>
                            <label
                                className={cn(
                                    'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 text-sm',
                                    checked && 'border-primary bg-muted/40',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    checked={checked}
                                    onChange={(event) =>
                                        toggle(role.id, event.target.checked)
                                    }
                                    className="size-5 accent-primary"
                                />
                                {role.name}
                            </label>
                        </li>
                    );
                })}
            </ul>

            <Input
                aria-label="Outra área de experiência"
                placeholder="Outra: qual?"
                className="min-h-11"
                maxLength={120}
                value={other}
                onChange={(event) => {
                    onOtherChange(event.target.value);

                    if (event.target.value) {
                        onNoExperienceChange(false);
                    }
                }}
            />

            <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 text-sm">
                <input
                    type="checkbox"
                    checked={noExperience}
                    onChange={(event) => setNoExperience(event.target.checked)}
                    className="size-5 accent-primary"
                />
                Ainda não tenho experiência, mas gostaria de aprender
            </label>
            <InputError message={error} />
        </fieldset>
    );
}
