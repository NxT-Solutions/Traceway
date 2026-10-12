import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Download a FIT course, then load it onto a Garmin through Garmin Connect or over USB.
 * The caller passes the download control, a request on the planner or signed links in the library.
 */
export function SendToDevice({
    open,
    onOpenChange,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Send to a Garmin</DialogTitle>
                    <DialogDescription>
                        The device follows the FIT course on its own map.
                        Download it, then load it one of two ways.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">{children}</div>
                <section className="grid gap-1 text-sm">
                    <h3 className="font-medium">With Garmin Connect</h3>
                    <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                        <li>
                            On connect.garmin.com, open Training &amp; Planning,
                            then Courses.
                        </li>
                        <li>Choose Import, pick the file, and save it.</li>
                        <li>
                            Choose Send to Device, then sync from the Garmin
                            Connect app.
                        </li>
                    </ol>
                </section>
                <section className="grid gap-1 text-sm">
                    <h3 className="font-medium">Over USB</h3>
                    <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                        <li>Plug the device into the computer.</li>
                        <li>
                            Copy the file into <code>Garmin/NewFiles</code>.
                        </li>
                        <li>
                            Eject it. The course is under Courses on the device.
                        </li>
                    </ol>
                </section>
                <p className="text-sm text-muted-foreground">
                    With the Traceway app on the Garmin, link it under{' '}
                    <a className="underline" href="/settings/devices">
                        Settings, Devices
                    </a>{' '}
                    and load routes over the phone instead.
                </p>
            </DialogContent>
        </Dialog>
    );
}
