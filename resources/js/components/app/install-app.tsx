import { EllipsisVertical, Share, Smartphone, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { cn } from '@/lib/utils';

const DISMISS_KEY = 'cue.install-prompt.dismissed-until';
const DISMISS_DAYS = 30;

function dismissedUntil(): number {
    try {
        return Number(localStorage.getItem(DISMISS_KEY) ?? 0);
    } catch {
        return 0;
    }
}

/**
 * How to put the app on this device: a one-tap "Instalar a app" where the browser
 * allows it (Chrome/Edge on Android and computers), otherwise the steps by hand.
 */
export function InstallAppInstructions() {
    const { platform, installed, canPrompt, install } = useInstallPrompt();

    if (installed) {
        return (
            <p className="text-sm text-muted-foreground">
                Está a usar a Cue App instalada neste dispositivo.
            </p>
        );
    }

    if (canPrompt) {
        const onInstall = async () => {
            if (await install()) {
                toast.success('A Cue App foi adicionada ao ecrã principal.');
            }
        };

        return (
            <div className="space-y-2">
                <p className="text-sm text-muted-foreground">
                    Abra a Cue App a partir de um ícone no ecrã principal, como
                    as outras apps, e receba os avisos da escala.
                </p>
                <Button className="min-h-11" onClick={() => void onInstall()}>
                    Instalar a app
                </Button>
            </div>
        );
    }

    if (platform === 'ios') {
        return (
            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                <li>Abra este site no Safari.</li>
                <li>
                    Toque em{' '}
                    <Share
                        className="inline size-4 align-text-bottom"
                        aria-label="Partilhar"
                    />{' '}
                    (Partilhar), em baixo ou no topo do ecrã.
                </li>
                <li>
                    Escolha <strong>Adicionar ao ecrã principal</strong> e toque
                    em <strong>Adicionar</strong>.
                </li>
            </ol>
        );
    }

    if (platform === 'android') {
        return (
            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                <li>Abra este site no Chrome.</li>
                <li>
                    Toque em{' '}
                    <EllipsisVertical
                        className="inline size-4 align-text-bottom"
                        aria-label="Mais opções"
                    />{' '}
                    no canto superior direito.
                </li>
                <li>
                    Escolha <strong>Instalar app</strong> ou{' '}
                    <strong>Adicionar ao ecrã principal</strong> e confirme.
                </li>
            </ol>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
            No telemóvel, abra este site no Chrome (Android) ou no Safari
            (iPhone) e adicione-o ao ecrã principal. No computador, use o ícone
            de instalar na barra de endereço do Chrome ou do Edge.
        </p>
    );
}

/**
 * Invites phone users to install the app (Android). iPhone users get the same
 * steps from the push prompt, which needs the installed app there. "Agora não"
 * hides it for 30 days; the steps stay on the Notificações page.
 */
export function InstallAppPrompt({ className }: { className?: string }) {
    const { platform, installed, canPrompt } = useInstallPrompt();
    const [hidden, setHidden] = useState(() => dismissedUntil() > Date.now());

    if (hidden || installed || (platform !== 'android' && !canPrompt)) {
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

    return (
        <section
            aria-labelledby="install-prompt-title"
            className={cn(
                'relative flex gap-3 rounded-xl border bg-muted/40 p-4 pr-12',
                className,
            )}
        >
            <Smartphone
                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="space-y-2">
                <h2 id="install-prompt-title" className="font-medium">
                    Instale a Cue App no telemóvel
                </h2>
                <InstallAppInstructions />
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
