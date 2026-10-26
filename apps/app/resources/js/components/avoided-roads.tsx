import type { AvoidedRoad } from '@/lib/avoided-roads';
import { avoidedPaint, roadName } from '@/lib/avoided-roads';

/**
 * The roads a rider skips. "Avoid a road" waits for the next tap on the map
 * and skips the way under it on every plan from then on, private to the
 * rider. Each road can be shown on the map or let back in. A road the drawn
 * route still uses is said, since a road with no way around it stays.
 */
export function AvoidedRoads({
    roads,
    picking,
    crossing = [],
    busy = false,
    onPick,
    onShow,
    onRemove,
}: {
    roads: AvoidedRoad[];
    /** True while the next tap on the map avoids the road under it. */
    picking: boolean;
    /** The ids of the roads the drawn route still uses. */
    crossing?: string[];
    /** True while a road is being found or let back in. */
    busy?: boolean;
    onPick: (picking: boolean) => void;
    onShow: (road: AvoidedRoad) => void;
    onRemove: (road: AvoidedRoad) => void;
}) {
    return (
        <section className="avoided" aria-label="Roads you avoid">
            <p className="sec-label">Roads you avoid</p>
            {roads.length === 0 ? (
                <p className="small">
                    A track that is mapped fine but rides badly? Avoid it, and
                    Traceway skips it on every plan.
                </p>
            ) : (
                <ul>
                    {roads.map((road) => {
                        const name = roadName(road);
                        const still = crossing.includes(road.id);

                        return (
                            <li key={road.id}>
                                <i
                                    aria-hidden="true"
                                    style={{ color: avoidedPaint.line }}
                                />
                                <span className="name">
                                    {name}
                                    {still ? (
                                        <span className="still">
                                            Still on this route: no way around
                                            it
                                        </span>
                                    ) : null}
                                </span>
                                <button
                                    type="button"
                                    className="textbtn"
                                    aria-label={`Show ${name} on the map`}
                                    onClick={() => onShow(road)}
                                >
                                    Show
                                </button>
                                <button
                                    type="button"
                                    className="textbtn"
                                    aria-label={`Remove ${name}`}
                                    disabled={busy}
                                    onClick={() => onRemove(road)}
                                >
                                    Remove
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
            <button
                type="button"
                className="import-file"
                aria-pressed={picking}
                disabled={busy && !picking}
                onClick={() => onPick(!picking)}
            >
                {picking
                    ? 'Tap the road on the map, or tap here to stop'
                    : busy
                      ? 'Finding the road…'
                      : 'Avoid a road'}
            </button>
        </section>
    );
}
