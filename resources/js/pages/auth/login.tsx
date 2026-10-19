import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { PhoneInput } from '@/components/phone-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            {status && (
                <output className="mb-4 block text-center text-sm font-medium text-green-700 dark:text-green-400">
                    {status}
                </output>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Número de telefone</Label>
                            <PhoneInput
                                id="phone"
                                name="phone"
                                required
                                autoComplete="tel-national"
                                placeholder="84 123 4567"
                                aria-describedby="phone-hint"
                                className="min-h-11"
                            />
                            <p
                                id="phone-hint"
                                className="text-sm text-muted-foreground"
                            >
                                O número que usou no cadastro.
                            </p>
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Palavra-passe</Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                    >
                                        Esqueceu-se?
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                className="min-h-11"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                defaultChecked
                                className="size-5"
                            />
                            <Label htmlFor="remember">
                                Manter sessão iniciada neste dispositivo
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 min-h-11 w-full"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Entrar
                        </Button>
                    </div>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                Ainda não é voluntário? Peça o link de cadastro a um
                coordenador.
            </p>
        </>
    );
}

Login.layout = {
    title: 'Entrar no Cue App',
    description: 'Indique o seu número de telefone e a palavra-passe.',
};
