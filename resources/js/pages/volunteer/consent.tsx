import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { CheckboxField } from '@/components/volunteer-form/fields';
import { accept } from '@/routes/volunteer/consent';

type Props = {
    declaration: { version: number; body: string };
};

/**
 * A new declaration version must be accepted before using the app (FR-002).
 */
export default function VolunteerConsent({ declaration }: Props) {
    const form = useForm({
        consent_accepted: false,
        consent_version: declaration.version,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(accept().url);
    };

    return (
        <>
            <Head title="Declaração actualizada" />

            <h1 className="text-2xl font-semibold">
                A declaração foi actualizada
            </h1>
            <p className="mt-1 text-muted-foreground">
                Leia a nova versão e aceite para continuar. Os seus turnos não
                mudam.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="max-h-96 overflow-y-auto rounded-lg border bg-muted/30 p-4 text-sm leading-6 whitespace-pre-line">
                    {declaration.body}
                </div>
                <CheckboxField
                    label="Li e aceito a declaração"
                    checked={form.data.consent_accepted}
                    onChange={(checked) =>
                        form.setData('consent_accepted', checked)
                    }
                    error={
                        form.errors.consent_accepted ??
                        form.errors.consent_version
                    }
                />
                <Button
                    type="submit"
                    className="min-h-11 w-full sm:w-auto"
                    disabled={form.processing || !form.data.consent_accepted}
                >
                    {form.processing && <Spinner />}
                    Aceitar e continuar
                </Button>
            </form>
        </>
    );
}
