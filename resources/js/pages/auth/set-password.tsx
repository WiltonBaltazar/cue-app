import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password-setup';

type Props = {
    token: string;
    name: string;
    passwordRules: string;
};

/**
 * Reached from a coordinator-issued link (FR-006).
 */
export default function SetPassword({ token, name, passwordRules }: Props) {
    return (
        <>
            <Head title="Definir palavra-passe" />

            <p className="text-center text-sm text-muted-foreground">
                Olá, {name.split(' ')[0]}.
            </p>

            <Form
                {...store.form(token)}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nova palavra-passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                className="min-h-11"
                                passwordrules={passwordRules}
                                aria-describedby="password-hint"
                            />
                            <p
                                id="password-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Pelo menos 8 caracteres.
                            </p>
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Repetir a palavra-passe
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                className="min-h-11"
                            />
                        </div>
                        <Button
                            type="submit"
                            className="min-h-11 w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Guardar palavra-passe
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

SetPassword.layout = {
    title: 'Definir palavra-passe',
    description:
        'Escolha a sua nova palavra-passe. O link só funciona uma vez.',
};
