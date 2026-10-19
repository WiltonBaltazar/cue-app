import { router, useForm } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/volunteer-form/fields';
import { formatLongDate } from '@/lib/format';
import { destroy, store } from '@/routes/data-removal';

/**
 * Ask for one's data to be removed, or cancel within the 7 days (FR-028). Asking needs
 * the password; afterwards the profile is anonymised automatically.
 */
export function DataRemovalCard({
    scheduledFor,
}: {
    scheduledFor: string | null;
}) {
    const form = useForm({ password: '' });
    const errors = form.errors as Record<string, string | undefined>;

    if (scheduledFor) {
        return (
            <section className="space-y-3 rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                <Heading
                    variant="small"
                    title="Remoção de dados agendada"
                    description={`Os seus dados serão removidos a ${formatLongDate(scheduledFor)}. Depois disso deixa de poder entrar e a sua participação fica só como "Voluntário #" sem nome.`}
                />
                <Button
                    variant="outline"
                    className="min-h-11"
                    onClick={() =>
                        router.delete(destroy().url, { preserveScroll: true })
                    }
                >
                    Cancelar pedido
                </Button>
            </section>
        );
    }

    return (
        <section className="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Remover os meus dados"
                description="Deixa a equipa e os seus dados pessoais são apagados 7 dias depois do pedido. Até lá pode cancelar. Os seus próximos turnos passam para os coordenadores substituírem."
            />
            <TextField
                label="Palavra-passe"
                type="password"
                autoComplete="current-password"
                value={form.data.password}
                onChange={(event) =>
                    form.setData('password', event.target.value)
                }
                hint="Para confirmar que é você."
                error={errors.password ?? errors.removal}
            />
            <ConfirmDialog
                title="Remover os seus dados?"
                description="Os coordenadores são avisados e os seus próximos turnos ficam por substituir. Daqui a 7 dias os seus dados são apagados e a conta deixa de existir."
                confirmLabel="Pedir remoção"
                destructive
                processing={form.processing}
                onConfirm={() =>
                    form.post(store().url, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    })
                }
            >
                <Button
                    variant="destructive"
                    className="min-h-11"
                    disabled={!form.data.password || form.processing}
                >
                    Pedir remoção
                </Button>
            </ConfirmDialog>
        </section>
    );
}
