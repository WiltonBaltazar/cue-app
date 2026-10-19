import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/volunteer-form/fields';
import { formatLongDate } from '@/lib/format';

/**
 * Pause or resume participation (FR-005a), used by the volunteer and by coordinators.
 * While paused nobody schedules the person; an optional return date ends the pause.
 */
export function PauseCard({
    paused,
    pausedUntil,
    pauseUrl,
    resumeUrl,
    subject,
}: {
    paused: boolean;
    pausedUntil: string | null;
    pauseUrl: string;
    resumeUrl: string;
    /** "a sua participação" for the volunteer, the person's name for coordinators. */
    subject?: string;
}) {
    const [until, setUntil] = useState('');
    const [error, setError] = useState<string | undefined>();
    const self = subject === undefined;

    if (paused) {
        return (
            <section className="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                <Heading
                    variant="small"
                    title={
                        self
                            ? 'Participação em pausa'
                            : `${subject} está em pausa`
                    }
                    description={
                        pausedUntil
                            ? `Regresso automático em ${formatLongDate(`${pausedUntil}T12:00:00Z`)}.`
                            : 'Sem data de regresso.'
                    }
                />
                <Button
                    className="min-h-11"
                    onClick={() =>
                        router.post(resumeUrl, {}, { preserveScroll: true })
                    }
                >
                    {self ? 'Voltar a participar' : 'Retomar participação'}
                </Button>
            </section>
        );
    }

    return (
        <section className="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Pausar participação"
                description={
                    self
                        ? 'Precisa de uma pausa? Não será escalado até voltar. Os turnos já publicados passam para os coordenadores substituírem.'
                        : `${subject} deixa de ser escalado. Os turnos já publicados ficam por substituir.`
                }
            />
            <TextField
                label="Regressar em"
                optional
                type="date"
                value={until}
                onChange={(event) => setUntil(event.target.value)}
                hint="Deixe vazio para retomar manualmente."
                error={error}
            />
            <ConfirmDialog
                title={
                    self ? 'Pausar a sua participação?' : `Pausar ${subject}?`
                }
                description="Os turnos publicados neste período ficam por substituir e os coordenadores são avisados."
                confirmLabel="Pausar"
                destructive
                onConfirm={() =>
                    router.post(
                        pauseUrl,
                        { until: until || null },
                        {
                            preserveScroll: true,
                            onError: (errors) =>
                                setError(errors.until ?? errors.volunteer),
                            onSuccess: () => setError(undefined),
                        },
                    )
                }
            >
                <Button variant="outline" className="min-h-11">
                    Pausar
                </Button>
            </ConfirmDialog>
        </section>
    );
}
