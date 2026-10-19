import DailyChart from '@/components/admin/daily-chart';
import { bytes, longDay } from '@/components/admin/format';
import Rating from '@/components/admin/rating';
import StatTile from '@/components/admin/stat-tile';
import TrafficPage from '@/components/admin/traffic-page';
import type { AdminBackups } from '@/types/admin';

/** Days between a backup day and today, both as UTC calendar days. */
function daysSince(date: string): number {
    const today = new Date().toISOString().slice(0, 10);

    return Math.round(
        (Date.parse(`${today}T00:00:00Z`) - Date.parse(`${date}T00:00:00Z`)) /
            86_400_000,
    );
}

function Health({ backups }: { backups: AdminBackups }) {
    if (backups.last_write === null) {
        return (
            <p className="health" role="alert">
                <Rating level="poor" label="No backups" />
                Nothing was written to {backups.bucket} in the last{' '}
                {backups.days} days. Check that Litestream runs on the server.
            </p>
        );
    }

    const age = daysSince(backups.last_write);
    const when =
        age <= 0
            ? 'today'
            : age === 1
              ? 'yesterday'
              : `on ${longDay(backups.last_write)}`;

    return age <= 1 ? (
        <p className="health">
            <Rating level="good" label="Being written" />
            Backups are being written. The last file went into {backups.bucket}{' '}
            {when}.
        </p>
    ) : (
        <p className="health" role="alert">
            <Rating level="poor" label="Stopped" />
            No backup since {when}. Check that Litestream runs on the server.
        </p>
    );
}

function Backups({ backups }: { backups: AdminBackups }) {
    if (backups.bucket === null) {
        return (
            <p className="note">
                Set <code>CLOUDFLARE_R2_BACKUP_BUCKET</code> to the bucket
                Litestream writes to.
            </p>
        );
    }

    return (
        <>
            <section className="panel" aria-labelledby="backups-h">
                <div className="panel-head">
                    <div>
                        <h2 id="backups-h">Database backups</h2>
                        <p>
                            Litestream copies each change to the database to
                            Cloudflare R2, and a full copy every day.
                        </p>
                    </div>
                </div>
                <Health backups={backups} />
                <div className="tiles">
                    <StatTile
                        label="Bucket size"
                        value={bytes(backups.bytes)}
                    />
                    <StatTile label="Files kept" value={backups.objects} />
                    <StatTile
                        label="Files written"
                        value={backups.writes}
                        detail={`last ${backups.days} days`}
                    />
                </div>
            </section>

            {backups.write_days.length > 0 ? (
                <section className="panel" aria-labelledby="writes-h">
                    <div className="panel-head">
                        <div>
                            <h2 id="writes-h">Files written</h2>
                            <p>Files that went into the bucket each day.</p>
                        </div>
                    </div>
                    <DailyChart
                        label={`Files written to the backup bucket each day, last ${backups.days} days`}
                        unit="files"
                        data={backups.write_days.map((day) => ({
                            date: day.date,
                            value: day.count,
                        }))}
                    />
                </section>
            ) : null}
        </>
    );
}

export default function TrafficBackups({
    insights,
}: {
    insights?: AdminBackups;
}) {
    return (
        <TrafficPage title="Admin backups" insights={insights}>
            {(backups) => <Backups backups={backups} />}
        </TrafficPage>
    );
}
