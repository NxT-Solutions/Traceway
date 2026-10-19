const whole = new Intl.NumberFormat('en-GB');
const compact = new Intl.NumberFormat('en-GB', {
    notation: 'compact',
    maximumFractionDigits: 1,
});
const dayMonth = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});
const fullDate = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});
const dateTime = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function count(value: number): string {
    return whole.format(value);
}

export function short(value: number): string {
    return value >= 10_000 ? compact.format(value) : whole.format(value);
}

/** A day from the server, such as 2026-10-19, read as that calendar day wherever the reader is. */
export function day(date: string): string {
    return dayMonth.format(new Date(`${date}T00:00:00Z`));
}

export function longDay(date: string): string {
    return fullDate.format(new Date(`${date}T00:00:00Z`));
}

export function when(iso: string | null): string {
    return iso === null ? 'Never' : dateTime.format(new Date(iso));
}

/** How long ago, in plain words, for "last seen". */
export function ago(iso: string | null): string {
    if (iso === null) {
        return 'Never';
    }

    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);

    if (minutes < 2) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours} h ago`;
    }

    const days = Math.round(hours / 24);

    return days < 60 ? `${days} days ago` : dateTime.format(new Date(iso));
}

export function bytes(value: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = value;
    let unit = 0;

    while (size >= 1000 && unit < units.length - 1) {
        size /= 1000;
        unit++;
    }

    return `${size.toFixed(size < 10 && unit > 0 ? 1 : 0)} ${units[unit]}`;
}
