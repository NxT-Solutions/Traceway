import type { SVGProps } from 'react';

/**
 * Swim, bike, and run in one mark: a wave over a wheel and two footprints. Drawn
 * like the lucide icons around it, so it sits at the same weight.
 */
export function MultisportIcon({
    size = 24,
    ...props
}: SVGProps<SVGSVGElement> & { size?: number }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            <path d="M2 6.5c1.67-1.33 3.33-1.33 5 0s3.33 1.33 5 0 3.33-1.33 5 0 3.33 1.33 5 0" />
            <circle cx="6.5" cy="16.5" r="4" />
            <circle cx="6.5" cy="16.5" r="0.5" />
            <ellipse cx="15" cy="15.5" rx="1.75" ry="2.75" />
            <ellipse cx="20" cy="18.5" rx="1.75" ry="2.75" />
        </svg>
    );
}
