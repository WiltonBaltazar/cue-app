import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { CircleAlert, CircleCheck } from 'lucide-react';
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
import { TextAreaField } from '@/components/volunteer-form/fields';
import { apiFetch } from '@/lib/query-client';
import { cn } from '@/lib/utils';
import { candidates as candidatesRoute } from '@/routes/app-api/positions';
import { store, update } from '@/routes/coord/assignments';

export type AssignTarget = {
    positionId: number;
    label: string;
    replacing: { id: number; name: string; lockVersion: number } | null;
};

type Candidate = {
    volunteerId: number;
    name: string;
    level: string | null;
    recentAssignments: number;
    eligible: boolean;
    violations: string[];
};

const VIOLATION_LABELS: Record<string, string> = {
    inactive: 'Não está activo',
    role: 'Não tem esta função',
    min_level: 'Nível abaixo do mínimo',
    availability: 'Não está disponível neste dia e período',
    absence: 'Tem uma ausência registada',
    frequency_cap: 'Ultrapassa a frequência que indicou',
    same_event: 'Já está escalado neste evento',
    time_overlap: 'Já está escalado noutro evento à mesma hora',
};

/**
 * Pick a volunteer for a position: ranked like the generator, with the rules each person
 * would break. Breaking a rule needs a note (FR-013).
 */
export function AssignDialog({
    target,
    onClose,
}: {
    target: AssignTarget | null;
    onClose: () => void;
}) {
    const [selected, setSelected] = useState<Candidate | null>(null);
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const query = useQuery({
        queryKey: [
            'position-candidates',
            target?.positionId,
            target?.replacing?.id,
        ],
        queryFn: () =>
            apiFetch<{ data: Candidate[] }>(
                candidatesRoute(target!.positionId, {
                    query: target!.replacing
                        ? { substituir: target!.replacing.id }
                        : {},
                }).url,
            ).then((body) => body.data),
        enabled: target !== null,
        staleTime: 0,
    });

    const close = () => {
        setSelected(null);
        setNote('');
        setErrors({});
        onClose();
    };

    const save = () => {
        if (!target || !selected) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: (errorBag: Record<string, string>) => setErrors(errorBag),
        };
        const data = {
            volunteer_id: selected.volunteerId,
            override_note: note || null,
        };

        if (target.replacing) {
            router.put(
                update(target.replacing.id).url,
                { ...data, lock_version: target.replacing.lockVersion },
                options,
            );
        } else {
            router.post(
                store().url,
                { ...data, position_id: target.positionId },
                options,
            );
        }
    };

    const hasWarnings = selected !== null && selected.violations.length > 0;

    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => !open && close()}
        >
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {target?.replacing
                            ? `Substituir ${target.replacing.name}`
                            : 'Escolher voluntário'}
                    </DialogTitle>
                    <DialogDescription>{target?.label}</DialogDescription>
                </DialogHeader>

                {query.isPending && (
                    <div className="flex justify-center p-6">
                        <Spinner />
                    </div>
                )}
                {query.isError && (
                    <p className="text-sm text-red-600">
                        Não foi possível carregar os candidatos.
                    </p>
                )}

                {query.data && (
                    <fieldset className="space-y-2">
                        <legend className="sr-only">Candidatos</legend>
                        {query.data.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum voluntário activo tem esta função.
                            </p>
                        )}
                        {query.data.map((candidate) => (
                            <label
                                key={candidate.volunteerId}
                                className={cn(
                                    'flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3',
                                    selected?.volunteerId ===
                                        candidate.volunteerId &&
                                        'border-primary bg-muted',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="candidate"
                                    className="mt-1 size-4 accent-primary"
                                    checked={
                                        selected?.volunteerId ===
                                        candidate.volunteerId
                                    }
                                    onChange={() => setSelected(candidate)}
                                />
                                <span className="flex-1">
                                    <span className="flex items-center gap-2 font-medium">
                                        {candidate.eligible ? (
                                            <CircleCheck
                                                className="size-4 text-green-600"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <CircleAlert
                                                className="size-4 text-amber-600"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {candidate.name}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {candidate.level ?? '—'} ·{' '}
                                        {candidate.recentAssignments} turnos nas
                                        últimas semanas
                                    </span>
                                    {candidate.violations.length > 0 && (
                                        <span className="block text-sm text-amber-700 dark:text-amber-400">
                                            {candidate.violations
                                                .map(
                                                    (violation) =>
                                                        VIOLATION_LABELS[
                                                            violation
                                                        ] ?? violation,
                                                )
                                                .join(' · ')}
                                        </span>
                                    )}
                                </span>
                            </label>
                        ))}
                    </fieldset>
                )}

                {hasWarnings && (
                    <TextAreaField
                        label="Nota"
                        optional
                        hint="Por exemplo, se já confirmou com a pessoa. Fica registada na auditoria."
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        error={errors.override_note}
                    />
                )}

                <InputError
                    message={
                        errors.lock_version ??
                        errors.volunteer_id ??
                        errors.position_id ??
                        errors.schedule ??
                        errors.violations
                    }
                />

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={close}
                    >
                        Cancelar
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={!selected || processing}
                        onClick={save}
                    >
                        {processing && <Spinner />}
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
