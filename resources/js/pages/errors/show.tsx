import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Não tem acesso a esta página',
        description:
            'Esta área está reservada. Se acha que é um erro, fale com um coordenador.',
    },
    404: {
        title: 'Página não encontrada',
        description: 'A página que procura não existe ou foi movida.',
    },
    429: {
        title: 'Demasiados pedidos',
        description: 'Aguarde um pouco e tente novamente.',
    },
    500: {
        title: 'Ocorreu um erro',
        description:
            'Algo correu mal do nosso lado. Tente novamente daqui a pouco.',
    },
    503: {
        title: 'Em manutenção',
        description: 'Voltamos dentro de momentos.',
    },
};

export default function ErrorPage({ status }: { status: number }) {
    const message = messages[status] ?? messages[500];

    return (
        <>
            <Head title={message.title} />

            <main className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center">
                <p className="text-sm font-semibold text-muted-foreground">
                    Erro {status}
                </p>
                <h1 className="text-2xl font-semibold">{message.title}</h1>
                <p className="max-w-md text-muted-foreground">
                    {message.description}
                </p>
                <Button asChild className="mt-2 min-h-11">
                    <Link href={home()}>Voltar ao início</Link>
                </Button>
            </main>
        </>
    );
}
