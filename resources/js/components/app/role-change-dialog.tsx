import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
import { destroy, store } from '@/routes/coord/coordinators';

/**
 * Give or take away the coordinator role. Coordinators see everyone's personal data,
 * so the change is confirmed with the acting coordinator's own password.
 */
export function RoleChangeDialog({
    userId,
    name,
    action,
    children,
}: {
    userId: number;
    name: string;
    action: 'grant' | 'revoke';
    /** The button that opens the dialog. */
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const grant = action === 'grant';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs: Record<string, string>) => setErrors(errs),
            onSuccess: () => {
                setOpen(false);
                setPassword('');
                setErrors({});
            },
        };

        if (grant) {
            router.post(store(userId).url, { password }, options);
        } else {
            router.delete(destroy(userId).url, {
                data: { password },
                ...options,
            });
        }
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
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {grant
                                ? `Dar o papel de coordenador a ${name}?`
                                : `Retirar o papel de coordenador a ${name}?`}
                        </DialogTitle>
                        <DialogDescription>
                            {grant
                                ? 'Passa a gerir escalas, cadastros e voluntários, e a ver os dados pessoais de todos. Pode retirar o papel quando quiser.'
                                : 'Deixa de ter acesso à gestão. Se também serve na equipa, continua nas escalas como voluntário.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`role-password-${userId}`}>
                            A sua palavra-passe
                        </Label>
                        <PasswordInput
                            id={`role-password-${userId}`}
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            autoComplete="current-password"
                            required
                        />
                        <InputError message={errors.password ?? errors.role} />
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
                            variant={grant ? 'default' : 'destructive'}
                            className="min-h-11"
                            disabled={processing || password === ''}
                        >
                            {processing && <Spinner />}
                            {grant ? 'Dar o papel' : 'Retirar papel'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
