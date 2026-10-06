import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Traceway"
            {...props}
        >
            <path
                d="M8.2 25.2 V15.4 a5.1 5.1 0 0 1 5.1 -5.1 h3.4 a5.1 5.1 0 0 1 5.1 5.1 v2.3"
                stroke="currentColor"
                strokeWidth="2.7"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="22.1" cy="23.5" r="2.45" fill="#97cb46" />
        </svg>
    );
}
