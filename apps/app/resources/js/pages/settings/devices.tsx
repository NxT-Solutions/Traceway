import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RouteServiceStatus } from '@/types/route-services';

type Device = {
    id: string;
    label: string;
    provider: string;
    linked_at: string | null;
    last_used_at: string | null;
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

export default function Devices({
    devices,
    services = [],
}: {
    devices: Device[];
    services?: RouteServiceStatus[];
}) {
    const form = useForm({ code: '', label: 'Garmin' });

    function link(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post('/settings/devices', {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
        });
    }

    return (
        <>
            <Head title="Devices" />

            <h1 className="sr-only">Devices</h1>

            <div className="card">
                <Heading
                    variant="small"
                    title="Link a device"
                    description="Open the Traceway app on your device. On a Garmin, that is Traceway in Connect IQ. It shows a six-character code. Type it here, and the device lists your saved routes and loads one as a course."
                />

                <form onSubmit={link} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="code">Code on the device</Label>
                        <Input
                            id="code"
                            name="code"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            autoComplete="one-time-code"
                            autoCapitalize="characters"
                            spellCheck={false}
                            maxLength={12}
                            placeholder="ABC123"
                            required
                        />
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
                                    <p className="text-muted-foreground">
                                        {device.provider}. Linked{' '}
                                        {written(device.linked_at)}. Last used{' '}
                                        {written(device.last_used_at)}.
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
                        description="Connect an account once. Then Send to device puts a route there in one tap."
                    />

                    <ul className="grid gap-3">
                        {services.map((service) => (
                            <li
                                key={service.service}
                                className="flex items-center justify-between gap-4"
                            >
                                <div className="text-sm">
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
                                </div>
                                {service.connected &&
                                !service.needs_reconnect ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            router.delete(
                                                `/settings/services/${service.service}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Disconnect {service.label}
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            window.location.assign(
                                                `/settings/services/${service.service}/connect`,
                                            )
                                        }
                                    >
                                        {service.connected
                                            ? `Connect ${service.label} again`
                                            : `Connect ${service.label}`}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
