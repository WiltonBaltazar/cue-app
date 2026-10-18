import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPhone } from '@/lib/format';
import { edit } from '@/routes/profile';

export default function Profile({ phone }: { phone: string }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Conta" />

            <h1 className="sr-only">Conta</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Conta"
                    description="O seu nome e o e-mail para recuperar a palavra-passe"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block min-h-11 w-full"
                                    defaultValue={auth.user?.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    Número de telefone
                                </Label>

                                <Input
                                    id="phone"
                                    className="mt-1 block min-h-11 w-full"
                                    value={formatPhone(phone)}
                                    readOnly
                                    aria-describedby="phone-hint"
                                />
                                <p
                                    id="phone-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    É o seu nome de utilizador. Para o alterar,
                                    fale com um coordenador.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail (opcional)</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block min-h-11 w-full"
                                    defaultValue={auth.user?.email ?? ''}
                                    name="email"
                                    autoComplete="email"
                                    aria-describedby="email-hint"
                                />
                                <p
                                    id="email-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    Com e-mail pode recuperar a palavra-passe
                                    sozinho.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <Button
                                className="min-h-11"
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                Guardar
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Conta',
            href: edit(),
        },
    ],
};
