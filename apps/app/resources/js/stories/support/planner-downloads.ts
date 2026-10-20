/** A file the page offered for download, caught before the browser saved it. */
export type CaughtFile = {
    filename: string;
    href: string;
    /** The file itself, when the page built it in the browser. */
    blob: Blob | null;
};

/**
 * Catches the files the planner hands the browser (a link with `download`, clicked from code) so a story can read
 * them instead of saving them to the viewer's disk. Returns the files caught so far and how to stop.
 */
export function catchDownloads(): { files: CaughtFile[]; restore: () => void } {
    const files: CaughtFile[] = [];
    const blobs = new Map<string, Blob>();
    const createObjectURL = URL.createObjectURL.bind(URL);
    const click = HTMLAnchorElement.prototype.click;

    URL.createObjectURL = (object: Blob | MediaSource) => {
        const href = createObjectURL(object);

        if (object instanceof Blob) {
            blobs.set(href, object);
        }

        return href;
    };
    HTMLAnchorElement.prototype.click = function (this: HTMLAnchorElement) {
        if (!this.hasAttribute('download')) {
            click.call(this);

            return;
        }

        files.push({
            filename: this.download,
            href: this.href,
            blob: blobs.get(this.href) ?? null,
        });
    };

    return {
        files,
        restore: () => {
            URL.createObjectURL = createObjectURL;
            HTMLAnchorElement.prototype.click = click;
        },
    };
}

/** Keeps what the page copies, since a test browser may not lend it the clipboard. Returns the copies and how to stop. */
export function catchCopies(): { copied: string[]; restore: () => void } {
    const copied: string[] = [];
    const stand = {
        writeText: (value: string) => {
            copied.push(value);

            return Promise.resolve();
        },
    };

    Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: stand,
    });

    return {
        copied,
        // The stand-in sits on this navigator; without it, the browser's own clipboard shows again.
        restore: () => {
            Reflect.deleteProperty(navigator, 'clipboard');
        },
    };
}
