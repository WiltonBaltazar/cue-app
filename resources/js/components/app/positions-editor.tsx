import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/components/volunteer-form/types';

export type PositionInput = {
    team_role_id: number | null;
    quantity: number;
    min_level: string | null;
};

const ANY_LEVEL = 'any';

type Props = {
    roles: { id: number; name: string }[];
    levels: Option[];
    value: PositionInput[];
    onChange: (positions: PositionInput[]) => void;
    errors: Record<string, string | undefined>;
};

/**
 * Positions needed at an event or template: role × quantity × minimum level (FR-008).
 */
export function PositionsEditor({
    roles,
    levels,
    value,
    onChange,
    errors,
}: Props) {
    const used = new Set(value.map((position) => position.team_role_id));

    const update = (index: number, patch: Partial<PositionInput>) =>
        onChange(
            value.map((position, i) =>
                i === index ? { ...position, ...patch } : position,
            ),
        );

    const add = () => {
        const nextRole = roles.find((role) => !used.has(role.id));
        onChange([
            ...value,
            {
                team_role_id: nextRole?.id ?? null,
                quantity: 1,
                min_level: null,
            },
        ]);
    };

    return (
        <fieldset className="space-y-3">
            <legend className="text-sm font-medium">
                Posições necessárias
            </legend>
            <p className="text-sm text-muted-foreground">
                Quantas pessoas são precisas em cada função, e o nível mínimo.
            </p>

            <ul className="space-y-3">
                {value.map((position, index) => (
                    <li
                        key={index}
                        className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_6rem_10rem_auto] sm:items-end"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor={`position-role-${index}`}>
                                Função
                            </Label>
                            <Select
                                value={
                                    position.team_role_id
                                        ? String(position.team_role_id)
                                        : undefined
                                }
                                onValueChange={(roleId) =>
                                    update(index, {
                                        team_role_id: Number(roleId),
                                    })
                                }
                            >
                                <SelectTrigger
                                    id={`position-role-${index}`}
                                    className="min-h-11"
                                >
                                    <SelectValue placeholder="Escolher função" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.id}
                                            value={String(role.id)}
                                            disabled={
                                                used.has(role.id) &&
                                                role.id !==
                                                    position.team_role_id
                                            }
                                        >
                                            {role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={
                                    errors[`positions.${index}.team_role_id`]
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`position-quantity-${index}`}>
                                Quantidade
                            </Label>
                            <Input
                                id={`position-quantity-${index}`}
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={20}
                                value={position.quantity}
                                onChange={(event) =>
                                    update(index, {
                                        quantity: Number(event.target.value),
                                    })
                                }
                                className="min-h-11"
                            />
                            <InputError
                                message={errors[`positions.${index}.quantity`]}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`position-level-${index}`}>
                                Nível mínimo
                            </Label>
                            <Select
                                value={position.min_level ?? ANY_LEVEL}
                                onValueChange={(level) =>
                                    update(index, {
                                        min_level:
                                            level === ANY_LEVEL ? null : level,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id={`position-level-${index}`}
                                    className="min-h-11"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ANY_LEVEL}>
                                        Qualquer
                                    </SelectItem>
                                    {levels.map((level) => (
                                        <SelectItem
                                            key={level.value}
                                            value={level.value}
                                        >
                                            {level.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11"
                            onClick={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                            aria-label="Remover posição"
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </li>
                ))}
            </ul>

            <InputError message={errors.positions} />

            <Button
                type="button"
                variant="outline"
                className="min-h-11"
                onClick={add}
                disabled={used.size >= roles.length}
            >
                <Plus aria-hidden="true" />
                Adicionar posição
            </Button>
        </fieldset>
    );
}
