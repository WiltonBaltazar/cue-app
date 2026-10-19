import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { Shift } from '@/components/app/shift-card';
import { SwapRequestDialog } from '@/components/app/swap-request-dialog';
import { Button } from '@/components/ui/button';
import { formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { decline } from '@/routes/volunteer/shifts';

/**
 * "Pedir troca" and "Não posso ir" for one of the volunteer's shifts (US4), on the
 * navy slate (`tone="slate"`) or on a light sheet.
 */
export function ShiftActions({
    shift,
    tone = 'light',
    declineOpen = false,
    className,
}: {
    shift: Shift;
    tone?: 'light' | 'slate';
    /** Open the "Não posso ir" confirmation straight away (72 h check push). */
    declineOpen?: boolean;
    className?: string;
}) {
    const [swapOpen, setSwapOpen] = useState(false);
    const label = `${shift.eventName}, função: ${shift.role}, ${formatLongDate(`${shift.date}T12:00:00Z`)}`;
    const onSlate = tone === 'slate';

    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            <Button
                variant="outline"
                className={cn(
                    'min-h-11 flex-1 sm:flex-none',
                    onSlate &&
                        'border-slate-line bg-transparent text-slate-foreground hover:bg-white/10 hover:text-slate-foreground dark:bg-transparent',
                )}
                onClick={() => setSwapOpen(true)}
            >
                Pedir troca
            </Button>
            <ConfirmDialog
                title="Não pode ir?"
                description={`${label}. Os coordenadores são avisados e o turno é oferecido aos colegas que podem assumir.`}
                confirmLabel="Não posso ir"
                destructive
                defaultOpen={declineOpen}
                onConfirm={() =>
                    router.post(
                        decline(shift.id).url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                <Button
                    variant="ghost"
                    className={cn(
                        'min-h-11 flex-1 sm:flex-none',
                        onSlate &&
                            'text-slate-foreground hover:bg-white/10 hover:text-slate-foreground',
                    )}
                >
                    Não posso ir
                </Button>
            </ConfirmDialog>
            <SwapRequestDialog
                assignmentId={shift.id}
                shiftLabel={label}
                open={swapOpen}
                onOpenChange={setSwapOpen}
            />
        </div>
    );
}
