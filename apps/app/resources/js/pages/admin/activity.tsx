import { Head, Link } from '@inertiajs/react';
import ActivityList from '@/components/admin/activity-list';
import { count } from '@/components/admin/format';
import { activity as activityRoute } from '@/routes/admin';
import type { AdminActivityPage } from '@/types/admin';

export default function Activity({
    activity,
}: {
    activity: AdminActivityPage;
}) {
    const page = (number: number) =>
        activityRoute(number > 1 ? { query: { page: number } } : undefined);

    return (
        <>
            <Head title="Admin activity" />

            <section className="panel" aria-labelledby="activity-h">
                <div className="panel-head">
                    <div>
                        <h2 id="activity-h">Activity</h2>
                        <p>
                            Every change made to an account from here or from
                            the server, newest first. Kept for a year.{' '}
                            {count(activity.total)} in all.
                        </p>
                    </div>
                </div>

                <ActivityList
                    entries={activity.entries}
                    empty="Nothing has been changed from the admin area yet."
                />

                {activity.pages > 1 ? (
                    <nav className="pager" aria-label="Pages">
                        <span>
                            Page {activity.page} of {activity.pages}
                        </span>
                        <span className="actions">
                            <Link
                                className="outline"
                                href={page(activity.page - 1)}
                                aria-disabled={activity.page <= 1}
                            >
                                Newer
                            </Link>
                            <Link
                                className="outline"
                                href={page(activity.page + 1)}
                                aria-disabled={activity.page >= activity.pages}
                            >
                                Older
                            </Link>
                        </span>
                    </nav>
                ) : null}
            </section>
        </>
    );
}
