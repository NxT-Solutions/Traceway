import { router } from '@inertiajs/react';
import { ExternalLink, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/connections';
import type { ConnectionSlug } from '@/types';

type Props = {
    slug: ConnectionSlug;
    label: string;
    /** True when Traceway holds keys from the service and asks it to withdraw them. */
    withdrawsAccess: boolean;
    /** True when the connection sends routes today. */
    sendsRoutes: boolean;
    /** Where the rider removes Traceway at the service, when Traceway cannot. */
    manageUrl: string | null;
    disabled?: boolean;
    /** The id of the sentence that says why it is disabled. */
    describedBy?: string;
    /**
     * Ids of what takes focus once the service is disconnected and this button is gone, such as the heading of
     * the list it moved to. The first one on the page wins.
     */
    focusAfter?: string[];
};

/**
 * Where focus goes after the dialog closes and its button is gone: the button again when the service is still
 * connected, or else the first of the places the page names.
 */
function focusTarget(slug: ConnectionSlug, ids: string[]): HTMLElement | null {
    const again = document.querySelector<HTMLButtonElement>(
        `[data-disconnect="${slug}"]`,
    );

    if (again !== null && !again.disabled) {
        return again;
    }

    for (const id of ids) {
        const element = document.getElementById(id);

        if (element !== null) {
            return element;
        }
    }

    return null;
}

/**
 * Disconnects one service after saying what happens: no more logging in with it, and what Traceway asks the
 * service to do or leaves to the rider.
 */
export default function DisconnectButton({
    slug,
    label,
    withdrawsAccess,
    sendsRoutes,
    manageUrl,
    disabled = false,
    describedBy,
    focusAfter = [],
}: Props) {
    const [open, setOpen] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);

    // Once disconnected, the row and this button leave the page, so focus would fall to the page itself.
    function returnFocus(event: Event): void {
        if (trigger.current?.isConnected) {
            return;
        }

        event.preventDefault();
        focusTarget(slug, focusAfter)?.focus();
    }

    function disconnect(): void {
        setDisconnecting(true);
        router.delete(destroy.url(slug), {
            preserveScroll: true,
            onFinish: () => {
                setDisconnecting(false);
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    ref={trigger}
                    type="button"
                    className="outline danger cx-action"
                    disabled={disabled}
                    aria-describedby={describedBy}
                    data-disconnect={slug}
                >
                    Disconnect {label}
                </button>
            </DialogTrigger>
            <DialogContent onCloseAutoFocus={returnFocus}>
                <DialogTitle className="pr-8">Disconnect {label}?</DialogTitle>
                <DialogDescription>
                    You can no longer log in with {label}.
                    {withdrawsAccess
                        ? ` Traceway also asks ${label} to remove its access.`
                        : ''}
                    {withdrawsAccess && sendsRoutes
                        ? ` Routes you sent stay in your ${label} library, but Traceway forgets them.`
                        : ''}
                </DialogDescription>
                {manageUrl === null ? null : (
                    <p className="text-sm text-muted-foreground">
                        Traceway cannot remove itself from your {label} account.{' '}
                        <a
                            className="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-4"
                            href={manageUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Remove Traceway at {label} too
                            <ExternalLink
                                className="size-4"
                                aria-hidden="true"
                            />
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                    </p>
                )}
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={disconnect}
                        disabled={disconnecting}
                        aria-busy={disconnecting || undefined}
                    >
                        {disconnecting ? (
                            <LoaderCircle
                                className="animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        ) : null}
                        Disconnect {label}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
