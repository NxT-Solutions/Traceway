import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import ConfirmEmailFirst from '@/components/connections/confirm-email-first';
import ConnectLink from '@/components/connections/connect-link';
import DisconnectBlocked from '@/components/connections/disconnect-blocked';
import DisconnectButton from '@/components/connections/disconnect-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { edit as editConnections } from '@/routes/connections';
import { edit } from '@/routes/devices';
import type {
    RouteServiceSlug,
    RouteServiceStatus,
} from '@/types/route-services';

type Device = {
    id: string;
    label: string;
    provider: string;
    linked_at: string | null;
    last_used_at: string | null;
    /** True from the moment the rider types the code until the device collects its token. */
    waiting: boolean;
};

function written(value: string | null): string {
    if (value === null) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const serviceNote: Record<string, string> = {
    wahoo: 'Routes go to your Wahoo app and sync to an ELEMNT or ACE.',
    ride_with_gps:
        'Routes go to your Ride with GPS library. From there, Send to Device puts one on a Karoo, Bryton, or Wahoo.',
};

/** The letters and digits a device shows: no 0, O, 1, I, or L. Lower case is let in and written in capitals. */
const codePattern = '^[A-HJKMNP-Za-hjkmnp-z2-9]*$';

/** A pasted code may come with a space or a dash between its two halves. */
function pastedCode(pasted: string): string {
    return pasted.replace(/[\s-]/g, '').toUpperCase();
}

/** How often the page asks again while a device has not collected its token. */
const waitingPoll = 3_000;

/** After a disconnect the button is gone, so focus goes to the list the service is still in. */
const focusAfterDisconnect = ['devices-send-to', 'devices-title'];

/** The sentence Connect and Disconnect point to while the rider's email waits to be confirmed. */
const confirmEmailId = 'devices-confirm-email';

export default function Devices({
    devices,
    services = [],
    disconnectBlocked = {},
    emailVerified,
    verificationSent,
    garminApp = null,
}: {
    devices: Device[];
    services?: RouteServiceStatus[];
    /** Why a service cannot be disconnected yet, such as it being the rider's last way to log in. */
    disconnectBlocked?: Partial<Record<RouteServiceSlug, string | null>>;
    /** False until the rider confirms their email. Connect and Disconnect wait for it. */
    emailVerified: boolean;
    /** True right after the rider asked for the confirmation email again. */
    verificationSent: boolean;
    /** The Traceway app in the Connect IQ store, or null when no store listing is set. */
    garminApp?: string | null;
}) {
    const form = useForm({ code: '', label: 'Garmin' });
    const locked = !emailVerified;
    const waiting = devices.some((device) => device.waiting);
    const poll = usePoll(
        waitingPoll,
        { only: ['devices'] },
        { autoStart: false },
    );

    // A linked device shows up once it collects its token, so the list asks again until it has.
    useEffect(() => {
        if (waiting) {
            poll.start();
        } else {
            poll.stop();
        }
    }, [waiting, poll.start, poll.stop]);

    function link(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post('/settings/devices', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <>
            <Head title="Devices" />

            <h1 id="devices-title" className="sr-only" tabIndex={-1}>
                Devices
            </h1>

            <div className="card">
                <Heading
                    variant="small"
                    title="Link a device"
                    description="First add the Traceway app to your Garmin from Garmin's Connect IQ store. Then open it on the device. It shows a six-character code. Type it here, and the device lists your saved routes and loads one as a course."
                />

                {garminApp !== null && (
                    <p className="desc">
                        No app on your Garmin yet?{' '}
                        <a
                            className="cx-link inline-flex items-center gap-1"
                            href={garminApp}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Get Traceway in Garmin's Connect IQ store
                            <ExternalLink size={14} aria-hidden="true" />
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                    </p>
                )}

                <div className="find-app">
                    <h3>Where Traceway is on the device</h3>
                    <ul>
                        <li>
                            <strong>Edge bike computer:</strong> open the menu
                            and choose Connect IQ. Then choose Apps, then
                            Traceway.
                        </li>
                        <li>
                            <strong>Forerunner or fēnix watch:</strong> on the
                            watch face, press the Start button, then scroll to
                            Traceway.
                        </li>
                        <li>
                            Not there? Sync the device in the Garmin Connect app
                            on your phone, then look again.
                        </li>
                    </ul>
                </div>

                <form onSubmit={link} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="code">Code on the device</Label>
                        <InputOTP
                            id="code"
                            name="code"
                            maxLength={6}
                            value={form.data.code}
                            onChange={(value) =>
                                form.setData('code', value.toUpperCase())
                            }
                            pattern={codePattern}
                            pasteTransformer={pastedCode}
                            autoComplete="one-time-code"
                            autoCapitalize="characters"
                            inputMode="text"
                            spellCheck={false}
                            aria-invalid={
                                form.errors.code === undefined
                                    ? undefined
                                    : true
                            }
                            containerClassName="code-slots"
                            required
                        >
                            <InputOTPGroup>
                                <InputOTPSlot index={0} className="code-slot" />
                                <InputOTPSlot index={1} className="code-slot" />
                                <InputOTPSlot index={2} className="code-slot" />
                            </InputOTPGroup>
                            <InputOTPGroup>
                                <InputOTPSlot index={3} className="code-slot" />
                                <InputOTPSlot index={4} className="code-slot" />
                                <InputOTPSlot index={5} className="code-slot" />
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={form.errors.code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="label">Name</Label>
                        <Input
                            id="label"
                            name="label"
                            value={form.data.label}
                            onChange={(event) =>
                                form.setData('label', event.target.value)
                            }
                            maxLength={40}
                            required
                        />
                        <InputError message={form.errors.label} />
                    </div>

                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Link device
                        </Button>
                    </div>
                </form>
            </div>

            <div className="card">
                <Heading
                    variant="small"
                    title="Linked devices"
                    description="A device reads your saved routes and nothing else. Remove it and its access stops at once."
                />

                {devices.length === 0 ? (
                    <p className="desc">No devices linked yet.</p>
                ) : (
                    <ul className="grid gap-3">
                        {devices.map((device) => (
                            <li
                                key={device.id}
                                className="flex items-center justify-between gap-4"
                            >
                                <div className="text-sm">
                                    <p className="font-medium">
                                        {device.label}
                                    </p>
                                    <p
                                        className="text-muted-foreground"
                                        aria-live="polite"
                                    >
                                        {device.waiting
                                            ? `${device.provider}. Waiting for the device. Keep Traceway open on it.`
                                            : `${device.provider}. Linked ${written(device.linked_at)}. Last used ${written(device.last_used_at)}.`}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            `/settings/devices/${device.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Remove
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {services.length === 0 ? null : (
                <div className="card">
                    <Heading
                        variant="small"
                        title="Send routes to"
                        id="devices-send-to"
                        focusable
                        description="Connect an account once. Then Send to device puts a route there in one tap, and you can log in with it too."
                    />

                    {locked ? (
                        <ConfirmEmailFirst
                            id={confirmEmailId}
                            sent={verificationSent}
                        />
                    ) : null}

                    <ul className="grid gap-4">
                        {services.map((service) => {
                            const blocked = service.connected
                                ? (disconnectBlocked[service.service] ?? null)
                                : null;
                            const blockedId = `devices-blocked-${service.service}`;
                            const why = [
                                blocked === null ? null : blockedId,
                                locked ? confirmEmailId : null,
                            ].filter((reason) => reason !== null);

                            return (
                                <li
                                    key={service.service}
                                    className="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div className="min-w-0 flex-[1_1_14rem] text-sm">
                                        <p className="font-medium">
                                            {service.label}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {service.connected
                                                ? service.needs_reconnect
                                                    ? `Traceway lost its link to ${service.label}. Connect it again.`
                                                    : `Connected ${written(service.connected_at)}. ${service.last_sent_at === null ? 'No route sent yet.' : `Last route sent ${written(service.last_sent_at)}.`}`
                                                : serviceNote[service.service]}
                                        </p>
                                        {blocked === null ? null : (
                                            <DisconnectBlocked
                                                id={blockedId}
                                                message={blocked}
                                                className="mt-1"
                                            />
                                        )}
                                    </div>
                                    <div className="cx-buttons">
                                        {!service.connected ||
                                        service.needs_reconnect ? (
                                            <ConnectLink
                                                slug={service.service}
                                                label={service.label}
                                                again={service.connected}
                                                returnTo={edit.url()}
                                                disabled={locked}
                                                describedBy={
                                                    locked
                                                        ? confirmEmailId
                                                        : undefined
                                                }
                                            />
                                        ) : null}
                                        {service.connected ? (
                                            <DisconnectButton
                                                slug={service.service}
                                                label={service.label}
                                                withdrawsAccess
                                                sendsRoutes
                                                manageUrl={null}
                                                disabled={
                                                    blocked !== null || locked
                                                }
                                                describedBy={
                                                    why.length === 0
                                                        ? undefined
                                                        : why.join(' ')
                                                }
                                                focusAfter={
                                                    focusAfterDisconnect
                                                }
                                            />
                                        ) : null}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>

                    <p className="cx-more">
                        <Link className="cx-link" href={editConnections()}>
                            All connections
                        </Link>
                    </p>
                </div>
            )}
        </>
    );
}
