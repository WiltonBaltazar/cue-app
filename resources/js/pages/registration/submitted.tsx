import { Head, Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

export default function RegistrationSubmitted() {
    return (
        <>
            <Head title="Cadastro enviado" />

            <div className="flex flex-col items-center gap-4 py-10 text-center">
                <CircleCheck
                    className="size-12 text-green-600"
                    aria-hidden="true"
                />
                <h1 className="text-2xl font-semibold">Cadastro enviado</h1>
                <p className="max-w-md text-muted-foreground">
                    Obrigado! Um coordenador vai rever o seu cadastro. Pode
                    entrar com o seu número de telefone e a palavra-passe para
                    acompanhar o estado.
                </p>
                <Button asChild className="min-h-11">
                    <Link href={login()}>Entrar</Link>
                </Button>
            </div>
        </>
    );
}
