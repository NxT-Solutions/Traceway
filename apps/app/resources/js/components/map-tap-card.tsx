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
    note,
    actions,
    onClose,
}: {
    /** Where the tap landed, in pixels from the map's top left corner. */
    x: number;
    y: number;
    /** The place around the tap, or null while it is being named. */
    name: string | null;
    /** Said instead of the actions while the spot has no road yet. */
    note: string | null;
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
        // A note in place of the actions makes the card shorter.
        void note;
        const card = cardRef.current;
        const map = card?.parentElement;

        if (card === null || map === null || map === undefined) {
            return;
        }

        const width = card.offsetWidth;
        const height = card.offsetHeight;
        const gap = 18;
        const edge = 12;
        const left = Math.min(
            Math.max(edge, x - width / 2),
            map.clientWidth - width - edge,
        );
        // Never under the search bar, where the bar would hide the name.
        const bar = map.querySelector<HTMLElement>('.map-search');
        const clear =
            bar !== null &&
            left < bar.offsetLeft + bar.offsetWidth &&
            left + width > bar.offsetLeft
                ? bar.offsetTop + bar.offsetHeight + edge
                : edge;
        const above = y - gap - height;
        const top =
            above >= clear
                ? above
                : Math.min(y + gap, map.clientHeight - height - edge);

        setPlace({ left, top: Math.max(clear, top) });
    }, [x, y, note]);

    // Escape puts the card away wherever the focus is, even while it is
    // still finding the nearest road.
    useEffect(() => {
        const away = (event: globalThis.KeyboardEvent): void => {
            if (event.key === 'Escape' && !event.defaultPrevented) {
                onClose();
            }
        };
        document.addEventListener('keydown', away);

        return () => document.removeEventListener('keydown', away);
    }, [onClose]);

    // Focus waits until the card is placed and has its actions: a hidden
    // button takes no focus.
    const ready = place !== null && note === null;

    useEffect(() => {
        // A rider typing in the search keeps the cursor there.
        const typing =
            document.activeElement instanceof HTMLInputElement ||
            document.activeElement instanceof HTMLTextAreaElement;

        if (ready && !typing) {
            cardRef.current
                ?.querySelector<HTMLButtonElement>('.fill')
                ?.focus({ preventScroll: true });
        }
    }, [ready]);

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
                {note === null ? null : (
                    <p className="tap-note" role="status">
                        {note}
                    </p>
                )}
                <div className="tap-actions" hidden={note !== null}>
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
