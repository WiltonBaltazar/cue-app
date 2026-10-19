import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import type { SkillInput, TeamRoleOption } from './types';

type Props = {
    roles: TeamRoleOption[];
    value: SkillInput[];
    onChange: (skills: SkillInput[]) => void;
    preferredRoleId: number | null;
    onPreferredChange: (roleId: number | null) => void;
    error?: string;
};

/**
 * Choose service areas, mark experience, and pick the area of greatest interest
 * (form section IV, FR-004).
 */
export function RolePicker({
    roles,
    value,
    onChange,
    preferredRoleId,
    onPreferredChange,
    error,
}: Props) {
    const selected = new Map(value.map((skill) => [skill.team_role_id, skill]));

    const toggle = (roleId: number, checked: boolean) => {
        if (checked) {
            onChange([
                ...value,
                { team_role_id: roleId, has_experience: false },
            ]);

            return;
        }

        onChange(value.filter((skill) => skill.team_role_id !== roleId));

        if (preferredRoleId === roleId) {
            onPreferredChange(null);
        }
    };

    const setExperience = (roleId: number, hasExperience: boolean) =>
        onChange(
            value.map((skill) =>
                skill.team_role_id === roleId
                    ? { ...skill, has_experience: hasExperience }
                    : skill,
            ),
        );

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Em que áreas gostaria de servir?
            </legend>
            <p className="text-sm text-muted-foreground">
                Escolha uma ou mais. Depois indique se já tem experiência e qual
                é a área de maior interesse.
            </p>

            <ul className="grid gap-2">
                {roles.map((role) => {
                    const skill = selected.get(role.id);
                    const checkboxId = `role-${role.id}`;

                    return (
                        <li
                            key={role.id}
                            className={cn(
                                'rounded-lg border p-3',
                                skill && 'border-primary bg-muted/40',
                            )}
                        >
                            <label
                                htmlFor={checkboxId}
                                className="flex min-h-11 cursor-pointer items-center gap-3"
                            >
                                <input
                                    id={checkboxId}
                                    type="checkbox"
                                    checked={skill !== undefined}
                                    onChange={(event) =>
                                        toggle(role.id, event.target.checked)
                                    }
                                    className="size-5 accent-primary"
                                />
                                <span className="font-medium">{role.name}</span>
                            </label>

                            {skill && (
                                <div className="mt-1 flex flex-wrap gap-x-6 gap-y-1 pl-8 text-sm">
                                    <label className="flex min-h-11 cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={skill.has_experience}
                                            onChange={(event) =>
                                                setExperience(
                                                    role.id,
                                                    event.target.checked,
                                                )
                                            }
                                            className="size-4 accent-primary"
                                        />
                                        Já tenho experiência
                                    </label>
                                    <label className="flex min-h-11 cursor-pointer items-center gap-2">
                                        <input
                                            type="radio"
                                            name="preferred_role_id"
                                            checked={
                                                preferredRoleId === role.id
                                            }
                                            onChange={() =>
                                                onPreferredChange(role.id)
                                            }
                                            className="size-4 accent-primary"
                                        />
                                        Área de maior interesse
                                    </label>
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
            <InputError message={error} />
        </fieldset>
    );
}
