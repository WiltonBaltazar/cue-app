import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * RTVAO's Alfa e Ómega mark. It sits on a white tile everywhere: its navy "A" would
 * disappear on the navy sidebar and in dark mode.
 */
export function RtvaoMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex aspect-square shrink-0 items-center justify-center rounded-md bg-white p-1 shadow-xs ring-1 ring-black/5',
                className,
            )}
        >
            <img
                src="/images/rtvao-mark.webp"
                alt=""
                width={243}
                height={256}
                className="h-full w-auto"
            />
        </span>
    );
}

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <RtvaoMark className="size-8" />
            <span className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">{name}</span>
                <span className="truncate text-xs opacity-70">RTVAO</span>
            </span>
        </>
    );
}
