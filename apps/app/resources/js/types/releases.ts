/** One unread release, as the bell in the header lists it. */
export type UnreadRelease = {
    id: number;
    tag: string;
    name: string;
    published_at: string | null;
    headline: string | null;
};

/** What changed since the rider last looked. Null for a guest. */
export type WhatsNew = {
    unread: number;
    items: UnreadRelease[];
};

/** One release on the What's new page, with its notes ready to show. */
export type ReleaseNotes = {
    id: number;
    tag: string;
    name: string;
    summary: string | null;
    published_at: string | null;
    read: boolean;
    html: string;
};
