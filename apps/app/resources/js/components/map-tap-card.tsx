import { useEffect, useLayoutEffect, useRef, useState } from 'react';

export type TapAction = {
    label: string;
    onSelect: () => void;
};

/**
 * A tap on the map asks before it moves a pin. A ring marks the spot and a
 * small card names it and offers what the tap can do there, so panning or a
 * stray tap never moves anything. Escape, the close button, or moving the
 * map puts it away.
 */
export function MapTapCard({
    x,
    y,
    name,
    actions,
    onClose,
}: {
    /** Where the tap landed, in pixels from the map's top left corner. */
    x: number;
    y: number;
    /** The place around the tap, or null while it is being named. */
    name: string | null;
    actions: TapAction[];
    onClose: () => void;
}) {
    const cardRef = useRef<HTMLDivElement>(null);
    const [place, setPlace] = useState<{ left: number; top: number } | null>(
        null,
    );

    // The card sits above the ring, and below it near the top edge, always
    // inside the map.
    useLayoutEffect(() => {
        const card = cardRef.current;
        const map = card?.parentElement;

        if (card === null || map === null || map === undefined) {
            return;
        }

        const width = card.offsetWidth;
        const height = card.offsetHeight;
        const gap = 18;
        const edge = 12;
        const above = y - gap - height;
        const top =
            above >= edge
                ? above
                : Math.min(y + gap, map.clientHeight - height - edge);
        const left = Math.min(
            Math.max(edge, x - width / 2),
            map.clientWidth - width - edge,
        );

        setPlace({ left, top: Math.max(edge, top) });
    }, [x, y]);

    // Focus waits until the card is placed: a hidden button takes no focus.
    const placed = place !== null;

    useEffect(() => {
        if (placed) {
            cardRef.current
                ?.querySelector<HTMLButtonElement>('.fill')
                ?.focus({ preventScroll: true });
        }
    }, [placed]);

    return (
        <>
            <span
                className="tap-ring"
                style={{ left: x, top: y }}
                aria-hidden="true"
            />
            <div
                ref={cardRef}
                className="tap-card"
                role="dialog"
                aria-label={name === null ? 'This spot' : name}
                style={
                    place === null
                        ? { visibility: 'hidden' }
                        : { left: place.left, top: place.top }
                }
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        event.stopPropagation();
                        onClose();
                    }
                }}
            >
                <div className="tap-head">
                    <b>{name ?? 'This spot'}</b>
                    <button
                        type="button"
                        className="ghostbtn"
                        aria-label="Close"
                        onClick={onClose}
                    >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 16 16"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 4 L12 12 M12 4 L4 12"
                                stroke="currentColor"
                                strokeWidth="1.6"
                                strokeLinecap="round"
                            />
                        </svg>
                    </button>
                </div>
                <div className="tap-actions">
                    {actions.map((action, index) => (
                        <button
                            key={action.label}
                            type="button"
                            className={index === 0 ? 'fill' : 'outline'}
                            onClick={action.onSelect}
                        >
                            {action.label}
                        </button>
                    ))}
                </div>
            </div>
        </>
    );
}
