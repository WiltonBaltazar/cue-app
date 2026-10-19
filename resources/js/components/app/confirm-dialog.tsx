import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    /** The element that opens the dialog, e.g. a button. */
    children: ReactNode;
    title: string;
    /** Must say who is affected, e.g. "12 voluntários serão notificados" (FR-039). */
    description: ReactNode;
    confirmLabel: string;
    cancelLabel?: string;
    destructive?: boolean;
    processing?: boolean;
    /** Open on mount, e.g. when arriving from a notification action. */
    defaultOpen?: boolean;
    onConfirm: () => void;
};

/**
 * Confirmation before destructive or broadcast actions (FR-039, constitution III).
 */
export function ConfirmDialog({
    children,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancelar',
    destructive = false,
    processing = false,
    defaultOpen = false,
    onConfirm,
}: Props) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline" className="min-h-11">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        className="min-h-11"
                        disabled={processing}
                        onClick={() => {
                            onConfirm();
                            setOpen(false);
                        }}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
