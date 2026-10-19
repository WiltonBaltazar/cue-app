import { useId } from 'react';
import type { SVGAttributes } from 'react';

// Film clapperboard; keep in sync with resources/icons/glyph.svg.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const maskId = useId();

    return (
        <svg
            {...props}
            viewBox="80 80 352 352"
            xmlns="http://www.w3.org/2000/svg"
        >
            <mask
                id={maskId}
                maskUnits="userSpaceOnUse"
                x="0"
                y="0"
                width="512"
                height="512"
            >
                <g fill="#fff" transform="translate(0 22)">
                    <path d="M112 262h288v118a28 28 0 0 1-28 28H140a28 28 0 0 1-28-28z" />
                    <rect x="112" y="200" width="288" height="50" rx="10" />
                    <rect
                        x="112"
                        y="138"
                        width="288"
                        height="50"
                        rx="10"
                        transform="rotate(-16 112 200)"
                    />
                </g>
                <g fill="#000" transform="translate(0 22)">
                    <path d="M168 200h40l-34 50h-40zM248 200h40l-34 50h-40zM328 200h40l-34 50h-40z" />
                    <path
                        d="M194 138h40l-34 50h-40zM274 138h40l-34 50h-40zM354 138h40l-34 50h-40z"
                        transform="rotate(-16 112 200)"
                    />
                </g>
            </mask>
            <rect width="512" height="512" mask={`url(#${maskId})`} />
        </svg>
    );
}
