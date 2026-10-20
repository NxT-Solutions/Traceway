import type { CSSProperties, ReactNode } from 'react';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * The planner's own scene (`.tw-planner`, or `.tw-library` for the route library) at the size of its content, so
 * planner.css styles a story exactly as it styles the map page. Paper or Ink follows the Appearance toolbar.
 */
export function PlannerScene({
    children,
    library = false,
    style,
}: {
    children: ReactNode;
    library?: boolean;
    style?: CSSProperties;
}) {
    const { resolvedAppearance } = useAppearance();

    return (
        <div
            className="tw-planner"
            data-theme={resolvedAppearance}
            style={{
                height: 'auto',
                minHeight: 0,
                overflow: 'visible',
                padding: 24,
                ...style,
            }}
        >
            {library ? (
                <div
                    className="tw-library"
                    style={{ height: 'auto', overflow: 'visible' }}
                >
                    {children}
                </div>
            ) : (
                children
            )}
        </div>
    );
}

/** A small map in the planner's colours with a chosen loop, another loop, a via point and the start pin. */
export function MapThumb() {
    return (
        <svg
            viewBox="0 0 160 100"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <rect className="m-base" width="160" height="100" />
            <rect
                className="m-wood"
                x="96"
                y="8"
                width="48"
                height="30"
                rx="4"
            />
            <rect
                className="m-res"
                x="14"
                y="58"
                width="34"
                height="26"
                rx="3"
            />
            <rect className="m-bld" x="20" y="64" width="8" height="6" />
            <rect className="m-bld" x="32" y="70" width="10" height="7" />
            <path
                className="m-water"
                d="M0 82 C40 74 70 92 110 80 S150 70 160 74"
            />
            <path className="m-minor" d="M0 30 H160" />
            <path className="m-minor" d="M62 0 V100" />
            <path className="m-major" d="M0 58 L160 46" />
            <path
                className="loser"
                style={{ opacity: 0.45, strokeDasharray: 'none' }}
                d="M62 30 L44 44 L50 70 L84 66 Z"
            />
            <path
                className="halo"
                style={{ strokeDasharray: 'none' }}
                d="M62 30 L88 40 L108 62 L84 74 L58 66 L56 46 Z"
            />
            <path
                className="win"
                style={{ strokeDasharray: 'none' }}
                d="M62 30 L88 40 L108 62 L84 74 L58 66 L56 46 Z"
            />
            <circle
                className="viadot"
                style={{ opacity: 1 }}
                cx="58"
                cy="66"
                r="4"
            />
            <circle className="pinp" cx="62" cy="30" r="5" />
        </svg>
    );
}
