import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SocialMediaInput } from './types';

type Props = {
    networks: string[];
    value: SocialMediaInput[];
    onChange: (profiles: SocialMediaInput[]) => void;
    errors: Record<string, string | undefined>;
};

const MAX_PROFILES = 10;

/**
 * Social media profiles, added one at a time as network + handle (question 4).
 */
export function SocialMediaList({ networks, value, onChange, errors }: Props) {
    const update = (index: number, changes: Partial<SocialMediaInput>) =>
        onChange(
            value.map((profile, current) =>
                current === index ? { ...profile, ...changes } : profile,
            ),
        );

    const add = () => {
        const unused =
            networks.find(
                (network) =>
                    !value.some((profile) => profile.network === network),
            ) ?? networks[0];

        onChange([...value, { network: unused, handle: '' }]);
    };

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Redes sociais
                <span className="font-normal text-muted-foreground">
                    {' '}
                    (opcional)
                </span>
            </legend>

            {value.map((profile, index) => (
                <div key={index} className="grid gap-1">
                    <div className="flex gap-2">
                        <Select
                            value={profile.network}
                            onValueChange={(network) =>
                                update(index, { network })
                            }
                        >
                            <SelectTrigger
                                aria-label={`Rede social ${index + 1}`}
                                className="min-h-11 w-36 shrink-0"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {networks.map((network) => (
                                    <SelectItem key={network} value={network}>
                                        {network}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            aria-label={`Perfil em ${profile.network}`}
                            placeholder={
                                profile.network === 'Outra'
                                    ? 'Rede e perfil'
                                    : '@nome ou link'
                            }
                            className="min-h-11"
                            maxLength={120}
                            value={profile.handle}
                            onChange={(event) =>
                                update(index, { handle: event.target.value })
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11 shrink-0"
                            aria-label={`Remover ${profile.network}`}
                            onClick={() =>
                                onChange(
                                    value.filter(
                                        (_, current) => current !== index,
                                    ),
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors[`social_media.${index}.handle`] ??
                            errors[`social_media.${index}.network`]
                        }
                    />
                </div>
            ))}

            {value.length < MAX_PROFILES && (
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 justify-self-start"
                    onClick={add}
                >
                    <Plus />
                    {value.length === 0
                        ? 'Adicionar rede social'
                        : 'Adicionar outra rede social'}
                </Button>
            )}
            <InputError message={errors.social_media} />
        </fieldset>
    );
}
