import { type KeyboardEvent, useEffect, useRef, useState } from 'react';
import {
    type FoundPlace,
    recentPlaces,
    rememberPlace,
    searchPlaces,
} from '@/lib/places';

export type PlaceAction = {
    label: string;
    copy: string;
    onSelect: () => void;
};

type Status =
    | { kind: 'idle' }
    | { kind: 'searching' }
    | { kind: 'found'; query: string; places: FoundPlace[] }
    | { kind: 'busy'; message: string };

/**
 * Finds a place by name. The public place search allows no search as you
 * type, so it searches when the rider presses Enter or the button. Under the
 * field: other ways to set the place, like picking it on the map, and the
 * places picked lately.
 */
export function PlaceSearch({
    near,
    storageKey,
    label,
    onPick,
    actions,
}: {
    /** The middle of the map, so places near it come first. */
    near: () => { latitude: number; longitude: number };
    /** Where this rider's recent places are kept. */
    storageKey: string;
    /** What the field asks for, read by screen readers. */
    label: string;
    onPick: (place: FoundPlace) => void;
    /** Other ways to set the place, above the results. */
    actions: PlaceAction[];
}) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState<Status>({ kind: 'idle' });
    const [recent] = useState(() => recentPlaces(storageKey));
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const asked = useRef(0);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    async function search(): Promise<void> {
        const words = query.trim();

        if (words.length < 2) {
            setStatus({
                kind: 'busy',
                message: 'Type at least two letters.',
            });

            return;
        }

        const ask = asked.current + 1;
        asked.current = ask;
        setStatus({ kind: 'searching' });
        const result = await searchPlaces(words, near());

        if (asked.current !== ask) {
            return;
        }

        setStatus(
            result.kind === 'found'
                ? { kind: 'found', query: words, places: result.places }
                : result,
        );
    }

    function pick(place: FoundPlace): void {
        rememberPlace(storageKey, place);
        onPick(place);
    }

    // The arrow keys walk from the field into the list and back.
    function walk(event: KeyboardEvent<HTMLElement>): void {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        const buttons = Array.from(
            listRef.current?.querySelectorAll('button') ?? [],
        );
        const at = buttons.indexOf(document.activeElement as HTMLButtonElement);
        const next =
            event.key === 'ArrowDown'
                ? at + 1
                : at === -1
                  ? buttons.length - 1
                  : at - 1;

        event.preventDefault();

        if (next < 0) {
            inputRef.current?.focus();

            return;
        }

        buttons[Math.min(next, buttons.length - 1)]?.focus();
    }

    const places =
        status.kind === 'found'
            ? status.places
            : status.kind === 'idle'
              ? recent
              : [];
    const heading =
        status.kind === 'found'
            ? 'Places'
            : recent.length > 0 && status.kind === 'idle'
              ? 'Recent'
              : null;
    const note =
        status.kind === 'searching'
            ? 'Searching…'
            : status.kind === 'busy'
              ? status.message
              : status.kind === 'found' && status.places.length === 0
                ? `Nothing called “${status.query}”. Try a town and country.`
                : status.kind === 'found'
                  ? `${status.places.length} ${status.places.length === 1 ? 'place' : 'places'} found.`
                  : 'Type a town, street, or place and press Enter.';

    return (
        // biome-ignore lint/a11y/noStaticElementInteractions: the arrow keys walk the list.
        <div className="place-search" onKeyDown={walk}>
            <form
                className="place-form"
                role="search"
                onSubmit={(event) => {
                    event.preventDefault();
                    void search();
                }}
            >
                <input
                    ref={inputRef}
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Town, street, or place"
                    aria-label={label}
                    aria-describedby="place-search-note"
                    enterKeyHint="search"
                    autoComplete="off"
                    maxLength={120}
                    className="h-10 min-w-0 flex-1 rounded-[10px] border border-border bg-background px-3 text-base text-foreground outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <button
                    type="submit"
                    className="outline sm"
                    disabled={status.kind === 'searching'}
                >
                    Search
                </button>
            </form>
            <p id="place-search-note" className="small" role="status">
                {note}
            </p>
            <ul className="choices place-list" ref={listRef}>
                {actions.map((action) => (
                    <li key={action.label}>
                        <button
                            type="button"
                            className="choice"
                            onClick={action.onSelect}
                        >
                            <span className="t">
                                <b>{action.label}</b>
                                <small>{action.copy}</small>
                            </span>
                        </button>
                    </li>
                ))}
                {heading === null ? null : (
                    <li className="group-label" aria-hidden="true">
                        {heading}
                    </li>
                )}
                {places.map((place) => (
                    <li
                        key={`${place.name}:${place.latitude}:${place.longitude}`}
                    >
                        <button
                            type="button"
                            className="choice"
                            onClick={() => pick(place)}
                        >
                            <span className="t">
                                <b>{place.name}</b>
                                {place.area === '' ? null : (
                                    <small>{place.area}</small>
                                )}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
