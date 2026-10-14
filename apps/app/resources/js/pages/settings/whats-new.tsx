import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    focusAfterRead,
    markAllReleasesRead,
    markReleaseRead,
    releaseAnchor,
    releaseDate,
} from '@/lib/releases';
import type { ReleaseNotes } from '@/types/releases';

function version(tag: string): string {
    return tag.replace(/^v(?=\d)/i, '');
}

export default function WhatsNew({ releases }: { releases: ReleaseNotes[] }) {
    const unread = releases.filter((release) => !release.read).length;

    return (
        <>
            <Head title="What's new" />
            <h1 className="sr-only">What's new</h1>
            <div className="card">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="What's new"
                        description="What changed in Traceway, newest first."
                    />
                    {unread > 0 ? (
                        <button
                            type="button"
                            className="outline"
                            onClick={() =>
                                markAllReleasesRead(() =>
                                    focusAfterRead(
                                        `${releaseAnchor(releases[0]?.tag ?? '')}-title`,
                                    ),
                                )
                            }
                        >
                            Mark all as read
                        </button>
                    ) : null}
                </div>
                {releases.length === 0 ? (
                    <p className="desc">
                        Nothing yet. New versions show up here.
                    </p>
                ) : null}
            </div>
            {releases.map((release) => (
                <article
                    key={release.id}
                    id={releaseAnchor(release.tag)}
                    className="card release"
                    aria-labelledby={`${releaseAnchor(release.tag)}-title`}
                >
                    <header className="release-head">
                        <div>
                            <p className="release-meta">
                                Version {version(release.tag)}
                                {release.published_at
                                    ? ` · ${releaseDate(release.published_at)}`
                                    : ''}
                                {release.read ? null : (
                                    <span className="release-new">New</span>
                                )}
                            </p>
                            <h2
                                id={`${releaseAnchor(release.tag)}-title`}
                                tabIndex={-1}
                            >
                                {release.name}
                            </h2>
                        </div>
                        {release.read ? null : (
                            <button
                                type="button"
                                className="outline"
                                onClick={() =>
                                    markReleaseRead(release.id, () =>
                                        focusAfterRead(
                                            `${releaseAnchor(release.tag)}-title`,
                                        ),
                                    )
                                }
                            >
                                Mark as read
                                <span className="sr-only"> {release.tag}</span>
                            </button>
                        )}
                    </header>
                    <div
                        className="release-notes"
                        // The server renders the notes from Markdown with raw HTML escaped and unsafe links removed.
                        dangerouslySetInnerHTML={{ __html: release.html }}
                    />
                </article>
            ))}
        </>
    );
}
