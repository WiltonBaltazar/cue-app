// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar palavra-passe" />

            {status && (
                <output className="mb-4 block text-center text-sm font-medium text-green-700 dark:text-green-400">
                    {status}
                </output>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    placeholder="nome@exemplo.com"
                                    className="min-h-11"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="min-h-11 w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    Enviar instruções
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <p className="text-center text-sm text-muted-foreground">
                    Não tem e-mail na conta? Peça a um coordenador um link para
                    definir uma nova palavra-passe.
                </p>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Ou volte para</span>
                    <TextLink href={login()}>entrar</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Recuperar palavra-passe',
    description: 'Indique o e-mail da sua conta para receber instruções.',
};
