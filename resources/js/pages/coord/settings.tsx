import { Head, useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { CheckboxField, TextField } from '@/components/volunteer-form/fields';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit, update } from '@/routes/coord/settings';

type Props = {
    registrationOpen: boolean;
    registrationClosesAt: string | null;
    registrationIsOpen: boolean;
    registrationUrl: string;
};

export default function CoordinatorSettings({
    registrationOpen,
    registrationClosesAt,
    registrationIsOpen,
    registrationUrl,
}: Props) {
    const [copiedText, copy] = useClipboard();
    const form = useForm({
        registration_open: registrationOpen,
        registration_closes_at: registrationClosesAt ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            registration_closes_at: data.registration_closes_at || null,
        }));
        form.put(update().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Definições" />

            <div className="space-y-6 p-4 lg:max-w-2xl">
                <h1 className="text-2xl font-semibold">Definições</h1>

                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-lg border p-4"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Heading
                            variant="small"
                            title="Link de cadastro"
                            description="O link público para novos voluntários."
                        />
                        <Badge
                            variant={
                                registrationIsOpen ? 'default' : 'secondary'
                            }
                        >
                            {registrationIsOpen ? 'Aberto' : 'Fechado'}
                        </Badge>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="registration-url">
                            Link para partilhar
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id="registration-url"
                                value={registrationUrl}
                                readOnly
                                className="min-h-11"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                onClick={() => void copy(registrationUrl)}
                                aria-label="Copiar link de cadastro"
                            >
                                {copiedText === registrationUrl ? (
                                    <Check />
                                ) : (
                                    <Copy />
                                )}
                            </Button>
                        </div>
                    </div>

                    <CheckboxField
                        label="Aceitar novos cadastros"
                        checked={form.data.registration_open}
                        onChange={(checked) =>
                            form.setData('registration_open', checked)
                        }
                        error={form.errors.registration_open}
                    />
                    <TextField
                        label="Fechar automaticamente em"
                        optional
                        type="datetime-local"
                        value={form.data.registration_closes_at}
                        onChange={(event) =>
                            form.setData(
                                'registration_closes_at',
                                event.target.value,
                            )
                        }
                        hint="Hora de Maputo. Deixe vazio para não fechar automaticamente."
                        error={form.errors.registration_closes_at}
                    />
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Guardar
                    </Button>
                </form>
            </div>
        </>
    );
}

CoordinatorSettings.layout = {
    breadcrumbs: [{ title: 'Definições', href: edit() }],
};
