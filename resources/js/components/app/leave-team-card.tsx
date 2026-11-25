import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { deactivate, destroy, reactivate } from '@/routes/coord/volunteers';

/**
 * Take someone out of the team (FR-005b): deactivating keeps their data and can be undone
 * by a coordinator; deleting can not.
 */
export function LeaveTeamCard({
    volunteerId,
    name,
    deactivated,
    isCoordinator,
}: {
    volunteerId: number;
    name: string;
    deactivated: boolean;
    /** Coordinators must lose the role before being deleted. */
    isCoordinator: boolean;
}) {
    const [error, setError] = useState<string | undefined>();

    const post = (url: string) =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.volunteer),
                onSuccess: () => setError(undefined),
            },
        );

    return (
        <section className="space-y-6 rounded-lg border border-destructive/40 p-4">
            <div className="space-y-3">
                <Heading
                    variant="small"
                    title={
                        deactivated
                            ? `${name} está desactivado`
                            : 'Desactivar voluntário'
                    }
                    description={
                        deactivated
                            ? 'Não aparece nas escalas nem pode ser escolhido. Os dados e o histórico mantêm-se.'
                            : 'Deixa de poder ser escolhido para as escalas, sem data de regresso. Só um coordenador o pode reactivar. Os dados e o histórico mantêm-se.'
                    }
                />
                {deactivated ? (
                    <Button
                        className="min-h-11"
                        onClick={() => post(reactivate(volunteerId).url)}
                    >
                        Reactivar
                    </Button>
                ) : (
                    <ConfirmDialog
                        title={`Desactivar ${name}?`}
                        description="Sai das escalas em rascunho. Os turnos já publicados ficam por substituir e os coordenadores são avisados."
                        confirmLabel="Desactivar"
                        destructive
                        onConfirm={() => post(deactivate(volunteerId).url)}
                    >
                        <Button variant="outline" className="min-h-11">
                            Desactivar
                        </Button>
                    </ConfirmDialog>
                )}
                <InputError message={error} />
            </div>

            <div className="space-y-3">
                <Heading
                    variant="small"
                    title="Eliminar voluntário"
                    description={
                        isCoordinator
                            ? 'Para eliminar esta pessoa, retire-lhe primeiro o papel de coordenador.'
                            : 'Apaga os dados pessoais e o acesso à aplicação. Os turnos passados ficam no histórico sem nome.'
                    }
                />
                {!isCoordinator && (
                    <DeleteDialog volunteerId={volunteerId} name={name} />
                )}
            </div>
        </section>
    );
}

function DeleteDialog({
    volunteerId,
    name,
}: {
    volunteerId: number;
    name: string;
}) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.delete(destroy(volunteerId).url, {
            data: { password },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs) => setErrors(errs),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setPassword('');
                    setErrors({});
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="destructive" className="min-h-11">
                    Eliminar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{`Eliminar ${name}?`}</DialogTitle>
                        <DialogDescription>
                            Não é possível desfazer. Os dados pessoais e o
                            acesso são apagados já, e os turnos publicados ficam
                            por substituir.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="delete-volunteer-password">
                            A sua palavra-passe
                        </Label>
                        <PasswordInput
                            id="delete-volunteer-password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            autoComplete="current-password"
                            required
                        />
                        <InputError
                            message={errors.password ?? errors.volunteer}
                        />
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            className="min-h-11"
                            disabled={processing || password === ''}
                        >
                            {processing && <Spinner />}
                            Eliminar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
