import { useEffect, useRef } from 'react';

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: HTMLElement,
                options: {
                    sitekey: string;
                    language?: string;
                    callback: (token: string) => void;
                    'expired-callback'?: () => void;
                },
            ) => string;
            remove: (widgetId: string) => void;
        };
    }
}

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

/**
 * Cloudflare Turnstile widget (FR-033). Renders nothing when no site key is configured.
 */
export function Turnstile({
    siteKey,
    onToken,
}: {
    siteKey: string | null;
    onToken: (token: string) => void;
}) {
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!siteKey || !container.current) {
            return;
        }

        let widgetId: string | null = null;
        const element = container.current;

        const render = () => {
            if (window.turnstile && widgetId === null) {
                widgetId = window.turnstile.render(element, {
                    sitekey: siteKey,
                    language: 'pt',
                    callback: onToken,
                    'expired-callback': () => onToken(''),
                });
            }
        };

        if (window.turnstile) {
            render();
        } else {
            let script = document.querySelector<HTMLScriptElement>(
                `script[src="${SCRIPT_SRC}"]`,
            );

            if (!script) {
                script = document.createElement('script');
                script.src = SCRIPT_SRC;
                script.async = true;
                document.head.appendChild(script);
            }

            script.addEventListener('load', render);
        }

        return () => {
            if (widgetId !== null) {
                window.turnstile?.remove(widgetId);
            }
        };
    }, [siteKey, onToken]);

    return siteKey ? <div ref={container} className="min-h-16" /> : null;
}
