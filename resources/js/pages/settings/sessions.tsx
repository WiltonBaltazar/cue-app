import { Head, router } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    destroy as destroySession,
    destroyOthers,
    index,
} from '@/routes/sessions';

type Session = {
    id: string;
    device: string;
    ip_address: string | null;
    last_active: string;
    is_current: boolean;
};

export default function Sessions({ sessions }: { sessions: Session[] }) {
    const others = sessions.filter((session) => !session.is_current);

    return (
        <>
            <Head title="Sessões" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Sessões activas"
                    description="Dispositivos onde a sua conta está aberta. Termine as que não reconhece."
                />

                <ul className="divide-y rounded-lg border">
                    {sessions.map((session) => (
                        <li
                            key={session.id}
                            className="flex items-center gap-3 p-4"
                        >
                            <MonitorSmartphone
                                className="size-6 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">
                                    {session.device}{' '}
                                    {session.is_current && (
                                        <Badge variant="secondary">
                                            Este dispositivo
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {session.ip_address ?? 'IP desconhecido'} ·
                                    activa {session.last_active}
                                </p>
                            </div>
                            {!session.is_current && (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() =>
                                        router.delete(
                                            destroySession(session.id).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Terminar
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>

                {others.length > 0 && (
                    <ConfirmDialog
                        title="Terminar as outras sessões?"
                        description={`A sua conta será fechada em ${others.length} ${
                            others.length === 1
                                ? 'outro dispositivo'
                                : 'outros dispositivos'
                        }. Este dispositivo continua com sessão iniciada.`}
                        confirmLabel="Terminar sessões"
                        destructive
                        onConfirm={() =>
                            router.delete(destroyOthers().url, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Button variant="destructive" className="min-h-11">
                            Terminar sessões noutros dispositivos
                        </Button>
                    </ConfirmDialog>
                )}
            </div>
        </>
    );
}

Sessions.layout = {
    breadcrumbs: [{ title: 'Sessões', href: index() }],
};
