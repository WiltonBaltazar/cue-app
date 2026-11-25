import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { ChevronDown, CircleAlert, CircleCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    CheckboxField,
    TextAreaField,
    TextField,
} from '@/components/volunteer-form/fields';
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

type Candidates = {
    data: Candidate[];
    /** Active people without the position's role. */
    others: Candidate[];
    role: string;
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
 * would break. People without the role are in a closed group, confirmed on saving and
 * optionally given the role (FR-013).
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
    const [addRole, setAddRole] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const query = useQuery({
        queryKey: [
            'position-candidates',
            target?.positionId,
            target?.replacing?.id,
        ],
        queryFn: () =>
            apiFetch<Candidates>(
                candidatesRoute(target!.positionId, {
                    query: target!.replacing
                        ? { substituir: target!.replacing.id }
                        : {},
                }).url,
            ),
        enabled: target !== null,
        staleTime: 0,
    });

    const select = (candidate: Candidate) => {
        setSelected(candidate);
        setAddRole(false);
    };

    const close = () => {
        setSelected(null);
        setNote('');
        setAddRole(false);
        setErrors({});
        onClose();
    };

    const hasWarnings = selected !== null && selected.violations.length > 0;
    const withoutRole =
        selected !== null && selected.violations.includes('role');

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
            add_role: withoutRole && addRole,
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
                        {query.data.data.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum voluntário activo tem esta função.
                            </p>
                        )}
                        {query.data.data.map((candidate) => (
                            <CandidateOption
                                key={candidate.volunteerId}
                                candidate={candidate}
                                selected={
                                    selected?.volunteerId ===
                                    candidate.volunteerId
                                }
                                onSelect={() => select(candidate)}
                            />
                        ))}
                    </fieldset>
                )}

                {query.data && query.data.others.length > 0 && (
                    <OtherPeople
                        people={query.data.others}
                        selected={selected}
                        onSelect={select}
                    />
                )}

                {withoutRole && (
                    <CheckboxField
                        label={`Acrescentar ${query.data?.role} ao perfil de ${selected.name}`}
                        hint="Fica com nível Iniciante e passa a entrar nas escalas automáticas desta função."
                        checked={addRole}
                        onChange={setAddRole}
                    />
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
                    {withoutRole ? (
                        <ConfirmDialog
                            title={`Escalar ${selected.name} sem a função?`}
                            description={
                                addRole
                                    ? `${selected.name} não tem ${query.data?.role}. Fica com este turno e a função é acrescentada ao perfil, com nível Iniciante.`
                                    : `${selected.name} não tem ${query.data?.role}. Fica com este turno só desta vez; o perfil não muda.`
                            }
                            confirmLabel="Escalar"
                            processing={processing}
                            onConfirm={save}
                        >
                            <Button className="min-h-11" disabled={processing}>
                                {processing && <Spinner />}
                                Guardar
                            </Button>
                        </ConfirmDialog>
                    ) : (
                        <Button
                            className="min-h-11"
                            disabled={!selected || processing}
                            onClick={save}
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Lower case, without accents, for searching names. */
function plain(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

function OtherPeople({
    people,
    selected,
    onSelect,
}: {
    people: Candidate[];
    selected: Candidate | null;
    onSelect: (candidate: Candidate) => void;
}) {
    const [search, setSearch] = useState('');
    const found = people.filter((person) =>
        plain(person.name).includes(plain(search.trim())),
    );

    return (
        <Collapsible>
            <CollapsibleTrigger asChild>
                <Button
                    variant="ghost"
                    className="group min-h-11 w-full justify-between"
                >
                    Outras pessoas, sem esta função ({people.length})
                    <ChevronDown
                        className="transition-transform group-data-[state=open]:rotate-180"
                        aria-hidden="true"
                    />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-2 pt-2">
                <TextField
                    label="Procurar por nome"
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <fieldset className="max-h-72 space-y-2 overflow-y-auto">
                    <legend className="sr-only">Outras pessoas</legend>
                    {found.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Ninguém com esse nome.
                        </p>
                    )}
                    {found.map((person) => (
                        <CandidateOption
                            key={person.volunteerId}
                            candidate={person}
                            selected={
                                selected?.volunteerId === person.volunteerId
                            }
                            onSelect={() => onSelect(person)}
                        />
                    ))}
                </fieldset>
            </CollapsibleContent>
        </Collapsible>
    );
}

function CandidateOption({
    candidate,
    selected,
    onSelect,
}: {
    candidate: Candidate;
    selected: boolean;
    onSelect: () => void;
}) {
    const shifts =
        candidate.recentAssignments === 1
            ? '1 turno nas últimas semanas'
            : `${candidate.recentAssignments} turnos nas últimas semanas`;

    return (
        <label
            className={cn(
                'flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3',
                selected && 'border-primary bg-muted',
            )}
        >
            <input
                type="radio"
                name="candidate"
                className="mt-1 size-4 accent-primary"
                checked={selected}
                onChange={onSelect}
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
                    {candidate.level
                        ? `${candidate.level} · ${shifts}`
                        : shifts}
                </span>
                {candidate.violations.length > 0 && (
                    <span className="block text-sm text-amber-700 dark:text-amber-400">
                        {candidate.violations
                            .map(
                                (violation) =>
                                    VIOLATION_LABELS[violation] ?? violation,
                            )
                            .join(' · ')}
                    </span>
                )}
            </span>
        </label>
    );
}
