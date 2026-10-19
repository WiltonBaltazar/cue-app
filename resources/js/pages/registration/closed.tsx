import { Head } from '@inertiajs/react';
import { CalendarX } from 'lucide-react';

export default function RegistrationClosed() {
    return (
        <>
            <Head title="Cadastro fechado" />

            <div className="flex flex-col items-center gap-4 py-10 text-center">
                <CalendarX
                    className="size-12 text-muted-foreground"
                    aria-hidden="true"
                />
                <h1 className="text-2xl font-semibold">
                    O cadastro está fechado
                </h1>
                <p className="max-w-md text-muted-foreground">
                    De momento não estamos a receber novos voluntários. Fale com
                    um coordenador da equipa de mídia para saber quando abre de
                    novo.
                </p>
            </div>
        </>
    );
}
