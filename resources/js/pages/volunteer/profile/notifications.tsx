import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { InstallAppInstructions } from '@/components/app/install-app';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { CheckboxField } from '@/components/volunteer-form/fields';
import { usePushSubscription } from '@/hooks/use-push-subscription';
import type { PushState } from '@/hooks/use-push-subscription';
import { formatLongDate } from '@/lib/format';
import { edit, update } from '@/routes/notification-preferences';

type Preference = {
    type: string;
    label: string;
    description: string;
    push: boolean;
};

type Device = {
    id: number;
    label: string;
    since: string | null;
};

const deviceStatus: Record<PushState, string> = {
    loading: 'A verificar…',
    unsupported:
        'Este navegador não recebe notificações. Instale a Cue App ou use outro navegador.',
    'needs-install':
        'No iPhone, adicione primeiro a Cue App ao ecrã principal (Partilhar → Adicionar ao ecrã principal) e abra-a a partir do ícone.',
    denied: 'As notificações estão bloqueadas neste navegador. Desbloqueie-as nas definições do navegador para este site.',
    available: 'Este dispositivo ainda não recebe notificações.',
    subscribed: 'Este dispositivo recebe notificações.',
};

export default function NotificationPreferences({
    preferences,
    devices,
}: {
    preferences: Preference[];
    devices: Device[];
}) {
    const push = usePushSubscription();
    const form = useForm<{ preferences: Record<string, boolean> }>({
        preferences: Object.fromEntries(
            preferences.map((preference) => [preference.type, preference.push]),
        ),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true });
    };

    const toggleDevice = async () => {
        if (push.state === 'subscribed') {
            await push.unsubscribe();
            toast.success('Notificações desactivadas neste dispositivo.');
        } else if (await push.subscribe()) {
            toast.success('Notificações activadas neste dispositivo.');
        } else {
            toast.error(
                'Não foi possível activar as notificações. Verifique as permissões do navegador.',
            );
        }
    };

    return (
        <>
            <Head title="Notificações" />

            <h1 className="sr-only">Notificações</h1>

            <div className="space-y-8">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Instalar a app"
                        description="Com a Cue App no ecrã principal, abre como as outras apps e recebe os avisos mesmo fechada."
                    />
                    <InstallAppInstructions />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Este dispositivo"
                        description="As notificações chegam a cada telemóvel ou computador onde as activar."
                    />
                    <output className="block text-sm">
                        {deviceStatus[push.state]}
                    </output>
                    {(push.state === 'available' ||
                        push.state === 'subscribed') && (
                        <Button
                            variant={
                                push.state === 'subscribed'
                                    ? 'outline'
                                    : 'default'
                            }
                            className="min-h-11"
                            disabled={push.busy}
                            onClick={() => void toggleDevice()}
                        >
                            {push.busy && <Spinner />}
                            {push.state === 'subscribed'
                                ? 'Desactivar neste dispositivo'
                                : 'Activar notificações'}
                        </Button>
                    )}
                    {devices.length > 0 && (
                        <div>
                            <h3 className="text-sm font-medium">
                                Dispositivos com notificações
                            </h3>
                            <ul className="mt-1 space-y-1 text-sm text-muted-foreground">
                                {devices.map((device) => (
                                    <li key={device.id}>
                                        {device.label}
                                        {device.since &&
                                            ` · desde ${formatLongDate(`${device.since}T12:00:00Z`)}`}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>

                <form onSubmit={submit} className="space-y-4">
                    <Heading
                        variant="small"
                        title="O que recebe como notificação"
                        description="O que desligar aqui continua a aparecer no sino da app."
                    />
                    <fieldset className="space-y-4">
                        <legend className="sr-only">
                            Tipos de notificação
                        </legend>
                        {preferences.map((preference) => (
                            <CheckboxField
                                key={preference.type}
                                label={preference.label}
                                hint={preference.description}
                                checked={form.data.preferences[preference.type]}
                                onChange={(checked) =>
                                    form.setData('preferences', {
                                        ...form.data.preferences,
                                        [preference.type]: checked,
                                    })
                                }
                            />
                        ))}
                    </fieldset>
                    {form.errors.preferences && (
                        <p className="text-sm text-destructive">
                            {form.errors.preferences}
                        </p>
                    )}
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

NotificationPreferences.layout = {
    breadcrumbs: [
        {
            title: 'Notificações',
            href: edit(),
        },
    ],
};
