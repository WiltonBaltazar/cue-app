import { Head, useForm } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import { TextAreaField } from '@/components/volunteer-form/fields';
import { formatDate } from '@/lib/format';
import { edit, store } from '@/routes/coord/consent';

type Props = {
    declaration: { version: number; body: string; publishedAt: string };
    volunteerCount: number;
};

/**
 * Editing the declaration publishes a new version that every volunteer must accept (FR-002).
 */
export default function CoordinatorConsent({
    declaration,
    volunteerCount,
}: Props) {
    const form = useForm({ body: declaration.body });
    const changed = form.data.body.trim() !== declaration.body.trim();

    return (
        <>
            <Head title="Declaração" />

            <div className="space-y-4 p-4 lg:max-w-3xl">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Declaração de consentimento
                    </h1>
                    <p className="text-muted-foreground">
                        Versão {declaration.version}, publicada a{' '}
                        {formatDate(declaration.publishedAt)}.
                    </p>
                </div>

                <TextAreaField
                    label="Texto da declaração"
                    rows={14}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    error={form.errors.body}
                />

                <ConfirmDialog
                    title="Publicar nova versão?"
                    description={`Todos os voluntários (${volunteerCount}) terão de aceitar novamente no próximo acesso. Os turnos não são afectados.`}
                    confirmLabel="Publicar"
                    processing={form.processing}
                    onConfirm={() =>
                        form.post(store().url, { preserveScroll: true })
                    }
                >
                    <Button
                        className="min-h-11"
                        disabled={!changed || form.processing}
                    >
                        Publicar nova versão
                    </Button>
                </ConfirmDialog>
            </div>
        </>
    );
}

CoordinatorConsent.layout = {
    breadcrumbs: [{ title: 'Declaração', href: edit() }],
};
