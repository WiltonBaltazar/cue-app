import { BellRing, Share, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { usePushSubscription } from '@/hooks/use-push-subscription';
import { cn } from '@/lib/utils';

const DISMISS_KEY = 'cue.push-prompt.dismissed-until';
const DISMISS_DAYS = 30;

function dismissedUntil(): number {
    try {
        return Number(localStorage.getItem(DISMISS_KEY) ?? 0);
    } catch {
        return 0;
    }
}

/**
 * Invites the user to turn on push on this device (contracts/notifications.md
 * `pushPrompt`). On iPhone/iPad in Safari it explains how to add the app to the
 * home screen first, since push only works there. "Agora não" hides it for 30 days.
 */
export function PushPrompt({ className }: { className?: string }) {
    const { state, busy, subscribe } = usePushSubscription();
    const [hidden, setHidden] = useState(() => dismissedUntil() > Date.now());

    if (hidden || (state !== 'available' && state !== 'needs-install')) {
        return null;
    }

    const dismiss = () => {
        try {
            localStorage.setItem(
                DISMISS_KEY,
                String(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000),
            );
        } catch {
            // Private mode: hide for this visit only.
        }

        setHidden(true);
    };

    const enable = async () => {
        if (await subscribe()) {
            toast.success('Notificações ativadas neste dispositivo.');
        } else {
            toast.error(
                'Não foi possível ativar as notificações. Verifique as permissões do navegador.',
            );
        }
    };

    return (
        <section
            aria-labelledby="push-prompt-title"
            className={cn(
                'relative flex gap-3 rounded-xl border bg-muted/40 p-4 pr-12',
                className,
            )}
        >
            <BellRing
                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="space-y-2">
                <h2 id="push-prompt-title" className="font-medium">
                    Receba os avisos da escala
                </h2>
                {state === 'needs-install' ? (
                    <p className="text-sm text-muted-foreground">
                        No iPhone, as notificações só funcionam com a Cue App no
                        ecrã principal. Toque em{' '}
                        <Share
                            className="inline size-4 align-text-bottom"
                            aria-label="Partilhar"
                        />{' '}
                        e depois em <strong>Adicionar ao ecrã principal</strong>
                        . Abra a app a partir do ícone e ative aqui as
                        notificações.
                    </p>
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Novas escalas, lembretes dos seus turnos e pedidos
                            de troca, mesmo com a app fechada.
                        </p>
                        <Button
                            className="min-h-11"
                            disabled={busy}
                            onClick={() => void enable()}
                        >
                            Ativar notificações
                        </Button>
                    </>
                )}
            </div>
            <Button
                variant="ghost"
                size="icon"
                className="absolute top-2 right-2 size-11"
                onClick={dismiss}
                aria-label="Agora não"
            >
                <X aria-hidden="true" />
            </Button>
        </section>
    );
}
