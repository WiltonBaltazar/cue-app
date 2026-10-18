import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar palavra-passe" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Palavra-passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                className="min-h-11"
                            />

                            <InputError message={errors.password} />
                        </div>

                        <Button
                            className="min-h-11 w-full"
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            Confirmar
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmar palavra-passe',
    description:
        'Esta é uma área protegida. Confirme a sua palavra-passe para continuar.',
};
