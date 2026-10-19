import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { swapCandidates } from '@/routes/app-api/assignments';
import { store } from '@/routes/swaps';

type Colleague = {
    volunteerId: number;
    displayName: string;
    level: string | null;
};

/**
 * Ask for a swap (FR-017): to every available colleague, or to one chosen colleague.
 * Only colleagues who can take the shift are listed.
 */
export function SwapRequestDialog({
    assignmentId,
    shiftLabel,
    open,
    onOpenChange,
}: {
    assignmentId: number;
    shiftLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [targetId, setTargetId] = useState<number | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const query = useQuery({
        queryKey: ['swap-candidates', assignmentId],
        queryFn: () =>
            apiFetch<{ data: Colleague[] }>(
                swapCandidates(assignmentId).url,
            ).then((body) => body.data),
        enabled: open,
        staleTime: 0,
    });

    const submit = () => {
        router.post(
            store().url,
            { assignment_id: assignmentId, target_volunteer_id: targetId },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errs) => setErrors(errs),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const colleagues = query.data ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pedir troca</DialogTitle>
                    <DialogDescription>{shiftLabel}</DialogDescription>
                </DialogHeader>

                <fieldset className="space-y-2">
                    <legend className="mb-2 text-sm font-medium">
                        Quem pode assumir?
                    </legend>
                    <label
                        className={cn(
                            'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3',
                            targetId === null && 'border-primary bg-muted',
                        )}
                    >
                        <input
                            type="radio"
                            name="swap-target"
                            checked={targetId === null}
                            onChange={() => setTargetId(null)}
                        />
                        <span>
                            Qualquer colega disponível
                            {query.isSuccess && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    ({colleagues.length})
                                </span>
                            )}
                        </span>
                    </label>

                    {query.isPending && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner /> A procurar colegas…
                        </p>
                    )}
                    {query.isError && (
                        <p className="text-sm text-destructive">
                            Não foi possível carregar os colegas. Tente de novo.
                        </p>
                    )}
                    {query.isSuccess && colleagues.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Neste momento nenhum colega pode assumir este turno.
                            O pedido fica aberto e os coordenadores são
                            avisados.
                        </p>
                    )}

                    <ul className="max-h-64 space-y-2 overflow-y-auto">
                        {colleagues.map((colleague) => (
                            <li key={colleague.volunteerId}>
                                <label
                                    className={cn(
                                        'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3',
                                        targetId === colleague.volunteerId &&
                                            'border-primary bg-muted',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="swap-target"
                                        checked={
                                            targetId === colleague.volunteerId
                                        }
                                        onChange={() =>
                                            setTargetId(colleague.volunteerId)
                                        }
                                    />
                                    <span className="flex-1">
                                        {colleague.displayName}
                                    </span>
                                    {colleague.level && (
                                        <span className="text-sm text-muted-foreground">
                                            {colleague.level}
                                        </span>
                                    )}
                                </label>
                            </li>
                        ))}
                    </ul>
                </fieldset>

                <InputError
                    message={errors.assignment_id ?? errors.target_volunteer_id}
                />

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={processing}
                        onClick={submit}
                    >
                        {processing && <Spinner />}
                        Enviar pedido
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
