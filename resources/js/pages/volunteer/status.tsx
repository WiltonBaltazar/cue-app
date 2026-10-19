import { Form, Head, Link } from '@inertiajs/react';
import { CircleAlert, Clock, CircleX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { resubmit } from '@/routes/volunteer';
import { edit as editProfileData } from '@/routes/volunteer/profile';

type Props = {
    status: 'pending' | 'needs_correction' | 'rejected';
    statusLabel: string;
    notes: string | null;
    submittedAt: string | null;
};

/**
 * What a volunteer sees until their registration is approved (FR-038).
 */
export default function VolunteerStatus({ status, notes, submittedAt }: Props) {
    return (
        <>
            <Head title="Estado do cadastro" />

            {status === 'pending' && (
                <section className="flex flex-col items-center gap-3 py-8 text-center">
                    <Clock
                        className="size-12 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h1 className="text-2xl font-semibold">
                        Cadastro em revisão
                    </h1>
                    <p className="max-w-md text-muted-foreground">
                        Um coordenador vai rever o seu cadastro
                        {submittedAt
                            ? `, enviado a ${formatDate(submittedAt)}`
                            : ''}
                        . Recebe uma notificação quando houver uma decisão.
                    </p>
                    <Button variant="outline" asChild className="min-h-11">
                        <Link href={editProfileData()}>Ver os meus dados</Link>
                    </Button>
                </section>
            )}

            {status === 'needs_correction' && (
                <section className="space-y-4 py-4">
                    <div className="flex items-center gap-3">
                        <CircleAlert
                            className="size-8 text-amber-600"
                            aria-hidden="true"
                        />
                        <h1 className="text-2xl font-semibold">
                            Falta corrigir o cadastro
                        </h1>
                    </div>
                    {notes && (
                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                            <p className="text-sm font-medium">
                                Mensagem do coordenador
                            </p>
                            <p className="mt-1 whitespace-pre-line">{notes}</p>
                        </div>
                    )}
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>Corrija os seus dados.</li>
                        <li>Volte aqui e reenvie o cadastro.</li>
                    </ol>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button asChild className="min-h-11">
                            <Link href={editProfileData()}>
                                Corrigir os meus dados
                            </Link>
                        </Button>
                        <Form {...resubmit.form()}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="min-h-11 w-full"
                                    disabled={processing}
                                >
                                    Reenviar cadastro
                                </Button>
                            )}
                        </Form>
                    </div>
                </section>
            )}

            {status === 'rejected' && (
                <section className="flex flex-col items-center gap-3 py-8 text-center">
                    <CircleX
                        className="size-12 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <h1 className="text-2xl font-semibold">
                        Cadastro não aprovado
                    </h1>
                    {notes ? (
                        <p className="max-w-md whitespace-pre-line text-muted-foreground">
                            {notes}
                        </p>
                    ) : (
                        <p className="max-w-md text-muted-foreground">
                            Para saber mais, fale com um coordenador da equipa
                            de mídia.
                        </p>
                    )}
                </section>
            )}
        </>
    );
}
