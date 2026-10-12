import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Device = {
    id: string;
    label: string;
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

export default function Devices({ devices }: { devices: Device[] }) {
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
                    description="Open the Traceway app on your Garmin. It shows a six-character code. Type it here, and the device lists your saved routes and loads one as a course."
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
                                        Linked {written(device.linked_at)}. Last
                                        used {written(device.last_used_at)}.
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
        </>
    );
}
