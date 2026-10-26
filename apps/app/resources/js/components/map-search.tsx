import {
    Building2,
    Clock,
    House,
    type LucideIcon,
    MapPin,
    Search,
    Signpost,
    X,
} from 'lucide-react';
import type * as maplibregl from 'maplibre-gl';
import {
    type KeyboardEvent,
    type RefObject,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import { correctSpelling, matchNames, namesOnMap } from '@/lib/map-names';
import {
    type FoundPlace,
    recentPlaces,
    rememberPlace,
    searchPlaces,
} from '@/lib/places';

/** Another thing to offer before the rider types, like "Use my location". */
export type SearchAction = {
    label: string;
    detail: string;
    icon: LucideIcon;
    onSelect: () => void;
};

type Status =
    | { kind: 'typing' }
    | { kind: 'searching' }
    | {
          kind: 'found';
          query: string;
          places: FoundPlace[];
          fixed: string | null;
      }
    | { kind: 'busy'; message: string; until?: number };

type Option =
    | { id: string; type: 'action'; action: SearchAction }
    | { id: string; type: 'search'; query: string }
    | { id: string; type: 'place'; place: FoundPlace; recent: boolean };

const kindIcon: Record<string, LucideIcon> = {
    address: House,
    street: Signpost,
    town: Building2,
    place: MapPin,
};

/**
 * The search bar on the map. While the rider types it suggests names from
 * the map around them, forgiving a typo, without asking any server. Enter
 * searches everywhere through Traceway, which the public place search allows
 * only on purpose, not on every key. A search that finds nothing tries again
 * with the spelling the map suggests.
 */
export function MapSearch({
    map,
    near,
    storageKey,
    placeholder,
    purpose,
    inputRef,
    actions,
    ask = null,
    onClearPurpose,
    onPick,
}: {
    map: () => maplibregl.Map | null;
    /** The middle of the map, so places near it come first. */
    near: () => { latitude: number; longitude: number };
    /** Where this rider's recent places are kept. */
    storageKey: string;
    placeholder: string;
    /** What the search is for, when a row asked: "Start", "Via". */
    purpose: string | null;
    inputRef: RefObject<HTMLInputElement | null>;
    /** Shown before the rider types. */
    actions: SearchAction[];
    /**
     * A place the rider asked for before they reached the planner, such as
     * the town typed on the home page. It is searched once, and the first
     * match is picked for them.
     */
    ask?: string | null;
    onClearPurpose: () => void;
    onPick: (place: FoundPlace) => void;
}) {
    const listId = useId();
    const boxRef = useRef<HTMLDivElement>(null);
    const asked = useRef(0);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState<Status>({ kind: 'typing' });
    const [nearby, setNearby] = useState<FoundPlace[]>([]);
    const [recent, setRecent] = useState<FoundPlace[]>([]);
    const [active, setActive] = useState(-1);
    const [now, setNow] = useState(() => Date.now());
    const until = status.kind === 'busy' ? status.until : undefined;

    // At the rider's limit for the minute, the note counts down to when
    // searching works again.
    useEffect(() => {
        if (until === undefined) {
            return;
        }

        setNow(Date.now());
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, [until]);

    // Suggestions from the map follow the typing, a beat behind it.
    useEffect(() => {
        const words = query.trim();

        if (words.length < 2) {
            setNearby([]);

            return;
        }

        const timer = window.setTimeout(() => {
            const current = map();
            setNearby(
                current === null ? [] : matchNames(words, namesOnMap(current)),
            );
        }, 120);

        return () => window.clearTimeout(timer);
    }, [query, map]);

    /** The places found, or null when busy or a newer search took over. */
    async function search(words: string): Promise<FoundPlace[] | null> {
        const ask = asked.current + 1;
        asked.current = ask;
        setActive(-1);
        setStatus({ kind: 'searching' });
        let result = await searchPlaces(words, near());

        // Busy means another place lookup had Traceway's turn: once more.
        if (result.kind === 'busy' && result.again) {
            await new Promise((resolve) => window.setTimeout(resolve, 1500));
            result = await searchPlaces(words, near());
        }
        let fixed: string | null = null;

        if (result.kind === 'found' && result.places.length === 0) {
            const current = map();
            const spelling =
                current === null
                    ? null
                    : correctSpelling(words, namesOnMap(current));

            if (spelling !== null) {
                const second = await searchPlaces(spelling, near());

                if (second.kind === 'found' && second.places.length > 0) {
                    result = second;
                    fixed = spelling;
                }
            }
        }

        if (asked.current !== ask) {
            return null;
        }

        setStatus(
            result.kind === 'found'
                ? { kind: 'found', query: words, places: result.places, fixed }
                : result,
        );

        return result.kind === 'found' ? result.places : null;
    }

    // The town from the home page. Found, the map goes there as if the rider
    // had picked it. Not found or busy, the search stays open and says so.
    const askedFor = useRef(false);

    // biome-ignore lint/correctness/useExhaustiveDependencies: once, for the place asked for on arrival
    useEffect(() => {
        const words = ask?.trim() ?? '';

        if (askedFor.current || words.length < 2) {
            return;
        }

        askedFor.current = true;
        setQuery(words);
        void search(words).then((places) => {
            const first = places?.[0];

            if (first === undefined) {
                setOpen(true);
            } else {
                pick(first);
            }
        });
    }, [ask]);

    function close(): void {
        setOpen(false);
        setActive(-1);
    }

    function pick(place: FoundPlace): void {
        if (place.area !== 'On the map') {
            rememberPlace(storageKey, place);
        }

        setQuery(place.name);
        close();
        inputRef.current?.blur();
        onPick(place);
    }

    const words = query.trim();
    const searched = status.kind === 'found' && status.query === words;
    const options: Option[] = [];

    if (words === '') {
        actions.forEach((action, index) => {
            options.push({ id: `${listId}-a${index}`, type: 'action', action });
        });
        recent.forEach((place, index) => {
            options.push({
                id: `${listId}-r${index}`,
                type: 'place',
                place,
                recent: true,
            });
        });
    } else {
        if (!searched && words.length >= 2) {
            options.push({ id: `${listId}-s`, type: 'search', query: words });
        }

        const found = searched ? status.places : [];
        found.forEach((place, index) => {
            options.push({
                id: `${listId}-p${index}`,
                type: 'place',
                place,
                recent: false,
            });
        });
        nearby
            .filter((place) => !found.some((item) => item.name === place.name))
            .forEach((place, index) => {
                options.push({
                    id: `${listId}-m${index}`,
                    type: 'place',
                    place,
                    recent: false,
                });
            });
    }

    function choose(option: Option): void {
        if (option.type === 'action') {
            close();
            inputRef.current?.blur();
            option.action.onSelect();

            return;
        }

        if (option.type === 'search') {
            void search(option.query);

            return;
        }

        pick(option.place);
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>): void {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);
            setActive((current) => {
                if (options.length === 0) {
                    return -1;
                }

                return event.key === 'ArrowDown'
                    ? (current + 1) % options.length
                    : current <= 0
                      ? options.length - 1
                      : current - 1;
            });

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();

            if (open) {
                close();

                return;
            }

            inputRef.current?.blur();

            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        const option = options[active];

        if (option !== undefined) {
            choose(option);

            return;
        }

        if (words.length < 2) {
            setStatus({
                kind: 'busy',
                message: 'Type at least two letters.',
            });

            return;
        }

        void search(words);
    }

    const note =
        status.kind === 'searching'
            ? 'Searching…'
            : status.kind === 'busy'
              ? status.until === undefined
                  ? status.message
                  : status.until <= now
                    ? 'You can search again now. Press Enter.'
                    : `You have used all 30 searches for this minute. You can search again in ${Math.ceil((status.until - now) / 1000)} seconds.`
              : searched && status.fixed !== null
                ? `Showing results for “${status.fixed}”.`
                : searched && status.places.length === 0
                  ? nearby.length > 0
                      ? `Nothing found for “${status.query}” beyond the map. These are near.`
                      : `Nothing found for “${status.query}”. Check the spelling, or add the town.`
                  : null;
    const expanded = open && (options.length > 0 || note !== null);

    return (
        <div
            ref={boxRef}
            role="search"
            className={expanded ? 'map-search open' : 'map-search'}
            onBlur={(event) => {
                if (!boxRef.current?.contains(event.relatedTarget as Node)) {
                    close();
                }
            }}
        >
            <div className="map-search-bar">
                <Search className="lead" size={18} aria-hidden="true" />
                {purpose === null ? null : (
                    <span className="purpose">
                        {purpose}
                        <button
                            type="button"
                            aria-label={`Stop searching for the ${purpose.toLowerCase()}`}
                            onClick={onClearPurpose}
                        >
                            <X size={12} aria-hidden="true" />
                        </button>
                    </span>
                )}
                <input
                    ref={inputRef}
                    type="text"
                    role="combobox"
                    aria-label={placeholder}
                    aria-expanded={expanded}
                    aria-controls={listId}
                    aria-autocomplete="list"
                    aria-activedescendant={options[active]?.id}
                    value={query}
                    placeholder={placeholder}
                    enterKeyHint="search"
                    autoComplete="off"
                    spellCheck={false}
                    maxLength={120}
                    onFocus={() => {
                        setRecent(recentPlaces(storageKey));
                        setOpen(true);
                    }}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setStatus({ kind: 'typing' });
                        setActive(-1);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                />
                {query === '' ? null : (
                    <button
                        type="button"
                        className="clear"
                        aria-label="Clear the search"
                        onClick={() => {
                            setQuery('');
                            setStatus({ kind: 'typing' });
                            inputRef.current?.focus();
                        }}
                    >
                        <X size={16} aria-hidden="true" />
                    </button>
                )}
            </div>
            <p className="map-search-note" role="status">
                {expanded ? note : null}
            </p>
            <div
                id={listId}
                role="listbox"
                aria-label="Places"
                className="map-search-list"
                hidden={!expanded || options.length === 0}
            >
                {options.map((option, index) => {
                    const Icon =
                        option.type === 'action'
                            ? option.action.icon
                            : option.type === 'search'
                              ? Search
                              : option.recent
                                ? Clock
                                : (kindIcon[option.place.kind ?? 'place'] ??
                                  MapPin);
                    const title =
                        option.type === 'action'
                            ? option.action.label
                            : option.type === 'search'
                              ? `Search everywhere for “${option.query}”`
                              : option.place.name;
                    const detail =
                        option.type === 'action'
                            ? option.action.detail
                            : option.type === 'search'
                              ? 'Press Enter'
                              : option.place.area;

                    return (
                        <div
                            key={option.id}
                            id={option.id}
                            role="option"
                            tabIndex={-1}
                            aria-selected={index === active}
                            className={index === active ? 'on' : undefined}
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActive(index)}
                            onClick={() => choose(option)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    choose(option);
                                }
                            }}
                        >
                            <Icon size={18} aria-hidden="true" />
                            <span className="t">
                                <b>{title}</b>
                                {detail === '' ? null : <small>{detail}</small>}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
