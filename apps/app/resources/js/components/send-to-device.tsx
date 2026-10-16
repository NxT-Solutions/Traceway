import { usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { csrfToken } from '@/lib/places';
import { cn } from '@/lib/utils';
import type {
    RouteServiceSlug,
    RouteServiceStatus,
} from '@/types/route-services';

/** A land leg of a multisport plan, which goes to a device on its own. */
export type DeviceLeg = { n: number; label: string };

type Brand = {
    id: string;
    name: string;
    file: 'fit' | 'gpx';
    /** The service the brand reads routes from, when Traceway can send there. */
    service?: RouteServiceSlug;
    intro: string;
    steps: Array<{ title: string; items: ReactNode[] }>;
};

const brands: Brand[] = [
    {
        id: 'garmin',
        name: 'Garmin',
        file: 'fit',
        intro: 'The device follows the FIT course on its own map. Download it, then load it one of two ways.',
        steps: [
            {
                title: 'With Garmin Connect',
                items: [
                    'On connect.garmin.com, open Training & Planning, then Courses.',
                    'Choose Import, pick the file, and save it.',
                    'Choose Send to Device, then sync from the Garmin Connect app.',
                ],
            },
            {
                title: 'Over USB',
                items: [
                    'Plug the device into the computer.',
                    <>
                        Copy the file into <code>Garmin/NewFiles</code>.
                    </>,
                    'Eject it. The course is under Courses on the device.',
                ],
            },
        ],
    },
    {
        id: 'wahoo',
        name: 'Wahoo',
        file: 'fit',
        service: 'wahoo',
        intro: 'Send the route to your Wahoo account. The ELEMNT or ACE picks it up the next time it syncs.',
        steps: [
            {
                title: 'Without connecting',
                items: [
                    'Download the FIT file on your phone.',
                    'Open it and share it to the Wahoo app.',
                    'It shows under Routes on the bike computer.',
                ],
            },
        ],
    },
    {
        id: 'karoo',
        name: 'Hammerhead Karoo',
        file: 'gpx',
        service: 'ride_with_gps',
        intro: 'The Karoo picks up routes you send to it from Ride with GPS.',
        steps: [
            {
                title: 'First time only',
                items: [
                    'On dashboard.hammerhead.io, link your Ride with GPS account.',
                ],
            },
            {
                title: 'For each route',
                items: [
                    'Send it to Ride with GPS here.',
                    'In Ride with GPS, choose Send to Device and pick the Karoo.',
                ],
            },
            {
                title: 'Without Ride with GPS',
                items: [
                    'Download the GPX file.',
                    'On dashboard.hammerhead.io, open Routes and import the file.',
                ],
            },
        ],
    },
    {
        id: 'bryton',
        name: 'Bryton',
        file: 'gpx',
        service: 'ride_with_gps',
        intro: 'Bryton Active brings in routes from Ride with GPS.',
        steps: [
            {
                title: 'First time only',
                items: [
                    'In the Bryton Active app, link your Ride with GPS account.',
                ],
            },
            {
                title: 'For each route',
                items: [
                    'Send it to Ride with GPS here.',
                    'Sync the route to the device in Bryton Active. If it does not show, make it public in Ride with GPS.',
                ],
            },
            {
                title: 'Without Ride with GPS',
                items: [
                    'Download the GPX file on your phone.',
                    'Open it and share it to Bryton Active.',
                ],
            },
        ],
    },
    {
        id: 'coros',
        name: 'COROS',
        file: 'gpx',
        intro: 'The COROS app takes the GPX file.',
        steps: [
            {
                title: 'In the COROS app',
                items: [
                    'Download the GPX file on your phone.',
                    'Open your routes in the COROS app and choose Import.',
                    'Pick the file, then sync the watch.',
                ],
            },
        ],
    },
    {
        id: 'suunto',
        name: 'Suunto',
        file: 'gpx',
        intro: 'The Suunto app takes the GPX file.',
        steps: [
            {
                title: 'In the Suunto app',
                items: [
                    'Download the GPX file on your phone.',
                    'In the Suunto app, open Map, then Routes, and import the file.',
                    'Keep Use in watch on, then sync.',
                ],
            },
        ],
    },
    {
        id: 'polar',
        name: 'Polar',
        file: 'gpx',
        intro: 'Polar Flow takes the GPX file.',
        steps: [
            {
                title: 'On flow.polar.com',
                items: [
                    'Download the GPX file.',
                    'Open Favorites and import the route.',
                    'Sync the watch with the Polar Flow app.',
                ],
            },
        ],
    },
    {
        id: 'amazfit',
        name: 'Amazfit',
        file: 'gpx',
        intro: 'The Zepp app takes the GPX file.',
        steps: [
            {
                title: 'In the Zepp app',
                items: [
                    'Download the GPX file on your phone.',
                    'In Zepp, open Profile, pick the watch, and import the file under its routes.',
                    'Sync the watch.',
                ],
            },
        ],
    },
    {
        id: 'other',
        name: 'Apple Watch or other',
        file: 'gpx',
        intro: 'Most route apps read GPX.',
        steps: [
            {
                title: 'In your route app',
                items: [
                    'Download the GPX file.',
                    'Open it in the watch app that follows routes, such as WorkOutDoors on Apple Watch.',
                ],
            },
        ],
    },
];

const brandKey = 'traceway.send-to-device.brand';

/** What to tell the rider when a send did not go through. */
function sendFailure(status: number, message: string | null): string {
    if (status === 429) {
        return 'That was a lot of sends. Try again in a minute.';
    }

    if (status === 404) {
        return 'This route is gone. Reload the page.';
    }

    if ((status === 409 || status === 422) && message !== null) {
        return message;
    }

    return 'The route could not be sent. Try again in a minute.';
}

/** Only a link to a web page the service gave, never a script. */
function isWebLink(value: unknown): value is string {
    return typeof value === 'string' && value.startsWith('https://');
}

function rememberedBrand(): string | null {
    try {
        return window.localStorage.getItem(brandKey);
    } catch {
        // Storage can be blocked. Then the rider picks again.
        return null;
    }
}

function rememberBrand(id: string): void {
    try {
        window.localStorage.setItem(brandKey, id);
    } catch {
        // Storage can be blocked. The pick still holds while the dialog is open.
    }
}

type SendState = {
    sending: boolean;
    ok: boolean;
    message: string;
    url: string | null;
};

/**
 * Gets a route onto any brand of device: a one-tap send where the brand reads
 * routes from a service Traceway can reach, and a file with steps everywhere.
 * The caller passes the FIT and GPX download controls, and how to get the
 * saved route's id: the library has it, the planner saves first.
 */
export function SendToDevice({
    open,
    onOpenChange,
    fit,
    gpx,
    legs = [],
    savedRouteId,
    sentTo = [],
    onSent,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fit: ReactNode;
    gpx: ReactNode;
    legs?: DeviceLeg[];
    savedRouteId?: () => Promise<string | null>;
    sentTo?: string[];
    /** Called after a send went through, so the caller can show it was sent. */
    onSent?: (service: RouteServiceSlug, routeId: string) => void;
}) {
    const services = usePage().props.routeServices ?? [];
    const [brandId, setBrandId] = useState<string | null>(null);
    const [sends, setSends] = useState<Record<string, SendState>>({});
    const [reconnect, setReconnect] = useState<RouteServiceSlug[]>([]);
    const brand = brands.find((candidate) => candidate.id === brandId);

    useEffect(() => {
        if (open && brandId === null) {
            setBrandId(rememberedBrand());
        }
    }, [open, brandId]);

    // What a send said belongs to that route. The dialog is reused for the
    // next route, so it starts clean each time it opens.
    useEffect(() => {
        if (!open) {
            setSends({});
        }
    }, [open]);

    function pick(id: string): void {
        setBrandId(id);
        rememberBrand(id);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Send to device</DialogTitle>
                    <DialogDescription>
                        Pick your device to see how the route gets onto it.
                    </DialogDescription>
                </DialogHeader>
                <div
                    role="group"
                    aria-label="Your device"
                    className="grid grid-cols-2 gap-2 sm:grid-cols-3"
                >
                    {brands.map((candidate) => (
                        <button
                            key={candidate.id}
                            type="button"
                            aria-pressed={candidate.id === brandId}
                            onClick={() => pick(candidate.id)}
                            className={cn(
                                'min-h-11 rounded-lg border px-3 py-2 text-left text-sm font-medium outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                candidate.id === brandId
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background hover:bg-accent',
                            )}
                        >
                            {candidate.name}
                        </button>
                    ))}
                </div>
                {brand === undefined ? null : (
                    <BrandSteps
                        brand={brand}
                        file={brand.file === 'fit' ? fit : gpx}
                        service={services.find(
                            (status) => status.service === brand.service,
                        )}
                        legs={legs}
                        savedRouteId={savedRouteId}
                        sent={
                            brand.service !== undefined &&
                            (sentTo.includes(brand.service) ||
                                sends[`${brand.service}:0`]?.ok === true)
                        }
                        onSent={onSent}
                        sends={sends}
                        setSends={setSends}
                        reconnect={reconnect}
                        setReconnect={setReconnect}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function BrandSteps({
    brand,
    file,
    service,
    legs,
    savedRouteId,
    sent,
    onSent,
    sends,
    setSends,
    reconnect,
    setReconnect,
}: {
    brand: Brand;
    file: ReactNode;
    service: RouteServiceStatus | undefined;
    legs: DeviceLeg[];
    savedRouteId?: () => Promise<string | null>;
    sent: boolean;
    onSent?: (service: RouteServiceSlug, routeId: string) => void;
    sends: Record<string, SendState>;
    setSends: (
        update: (
            current: Record<string, SendState>,
        ) => Record<string, SendState>,
    ) => void;
    reconnect: RouteServiceSlug[];
    setReconnect: (
        update: (current: RouteServiceSlug[]) => RouteServiceSlug[],
    ) => void;
}) {
    const [connecting, setConnecting] = useState(false);
    const cloud = service !== undefined && savedRouteId !== undefined;

    async function connect(slug: RouteServiceSlug): Promise<void> {
        if (savedRouteId === undefined) {
            return;
        }

        setConnecting(true);

        // The route waits in Saved routes while the rider signs in at the service.
        const id = await savedRouteId();

        if (id === null) {
            setConnecting(false);

            return;
        }

        window.location.assign(
            `/settings/services/${slug}/connect?return=${encodeURIComponent('/saved')}`,
        );
    }

    async function send(slug: RouteServiceSlug, leg: number): Promise<void> {
        if (savedRouteId === undefined) {
            return;
        }

        const key = `${slug}:${leg}`;
        const update = (state: SendState) =>
            setSends((current) => ({ ...current, [key]: state }));

        update({ sending: true, ok: false, message: '', url: null });

        const id = await savedRouteId();

        if (id === null) {
            update({
                sending: false,
                ok: false,
                message: 'The route could not be saved. Try again.',
                url: null,
            });

            return;
        }

        try {
            const response = await fetch(
                `/routes/saved/${encodeURIComponent(id)}/send/${slug}`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(leg > 0 ? { leg } : {}),
                },
            );
            const body: unknown = await response.json().catch(() => null);
            const record =
                typeof body === 'object' && body !== null
                    ? (body as Record<string, unknown>)
                    : {};
            const message =
                typeof record.message === 'string' ? record.message : null;

            if (response.status === 409) {
                setReconnect((current) => [...current, slug]);
            }

            update({
                sending: false,
                ok: response.ok,
                message: response.ok
                    ? (message ?? 'Sent.')
                    : sendFailure(response.status, message),
                url: response.ok && isWebLink(record.url) ? record.url : null,
            });

            if (response.ok) {
                onSent?.(slug, id);
            }
        } catch {
            update({
                sending: false,
                ok: false,
                message: 'The route could not be sent. Check the connection.',
                url: null,
            });
        }
    }

    return (
        <div className="grid gap-4">
            <p className="text-sm text-muted-foreground">{brand.intro}</p>
            {cloud ? (
                <CloudSend
                    service={service}
                    needsConnecting={
                        !service.connected ||
                        service.needs_reconnect ||
                        reconnect.includes(service.service)
                    }
                    connecting={connecting}
                    onConnect={() => void connect(service.service)}
                    onSend={(leg) => void send(service.service, leg)}
                    legs={legs}
                    sent={sent}
                    sends={sends}
                    notice={
                        Object.entries(sends).find(
                            ([key, state]) =>
                                key.startsWith(`${service.service}:`) &&
                                !state.ok &&
                                state.message !== '',
                        )?.[1].message ?? ''
                    }
                />
            ) : null}
            <section className="grid gap-2">
                <h3 className="text-sm font-medium">
                    {brand.file === 'fit' ? 'FIT course' : 'GPX file'}
                </h3>
                <div className="grid gap-2">{file}</div>
            </section>
            {brand.steps.map((group) => (
                <section key={group.title} className="grid gap-1 text-sm">
                    <h3 className="font-medium">{group.title}</h3>
                    <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                        {group.items.map((item, index) => (
                            <li key={index}>{item}</li>
                        ))}
                    </ol>
                </section>
            ))}
            {brand.id === 'garmin' ? (
                <p className="text-sm text-muted-foreground">
                    With the Traceway app on the Garmin, link it under{' '}
                    <a className="underline" href="/settings/devices">
                        Settings, Devices
                    </a>{' '}
                    and load routes over the phone instead.
                </p>
            ) : null}
        </div>
    );
}

function CloudSend({
    service,
    needsConnecting,
    connecting,
    onConnect,
    onSend,
    legs,
    sent,
    sends,
    notice,
}: {
    service: RouteServiceStatus;
    needsConnecting: boolean;
    connecting: boolean;
    onConnect: () => void;
    onSend: (leg: number) => void;
    legs: DeviceLeg[];
    sent: boolean;
    sends: Record<string, SendState>;
    /** Why the last send failed, kept when the section turns into Connect. */
    notice: string;
}) {
    // One send at a time, so two sends never refresh the same sign-in at once.
    const busy = Object.values(sends).some((state) => state.sending);

    if (needsConnecting) {
        return (
            <section className="grid gap-2 rounded-lg border p-3">
                <p className="text-sm">
                    {service.connected
                        ? `Traceway lost its link to ${service.label}. Connect it again.`
                        : `Connect your ${service.label} account once. Then a route goes there in one tap.`}
                </p>
                <p role="status" className="text-sm text-destructive">
                    {notice}
                </p>
                <Button
                    type="button"
                    disabled={connecting}
                    aria-busy={connecting}
                    onClick={onConnect}
                >
                    {connecting
                        ? 'Opening…'
                        : service.connected
                          ? `Connect ${service.label} again`
                          : `Connect ${service.label}`}
                </Button>
            </section>
        );
    }

    const targets =
        legs.length > 0
            ? legs.map((leg) => ({
                  leg: leg.n,
                  label: `Send leg ${leg.n}, ${leg.label}`,
              }))
            : [
                  {
                      leg: 0,
                      label: sent
                          ? `Send to ${service.label} again`
                          : `Send to ${service.label}`,
                  },
              ];

    return (
        <section className="grid gap-2 rounded-lg border p-3">
            {targets.map((target) => {
                const state = sends[`${service.service}:${target.leg}`];

                return (
                    <div key={target.leg} className="grid gap-1">
                        <Button
                            type="button"
                            disabled={busy}
                            aria-busy={state?.sending === true}
                            onClick={() => onSend(target.leg)}
                        >
                            {state?.sending === true
                                ? 'Sending…'
                                : target.label}
                        </Button>
                        {/* Kept in place while empty, so a screen reader reads the result when it comes. */}
                        <p
                            role="status"
                            className={cn(
                                'text-sm',
                                state?.ok === false
                                    ? 'text-destructive'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {state?.message}{' '}
                            {state !== undefined && state.url !== null ? (
                                <a
                                    className="underline"
                                    href={state.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Open in {service.label}
                                </a>
                            ) : null}
                        </p>
                    </div>
                );
            })}
        </section>
    );
}
