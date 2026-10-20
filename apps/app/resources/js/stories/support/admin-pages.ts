import type {
    ActivityEntry,
    AdminActivityPage,
    AdminBackups,
    AdminBots,
    AdminOverview,
    AdminRequests,
    AdminSecurity,
    AdminSpeed,
    AdminTraffic,
    AdminUser,
    AdminUserPage,
    AdminUserRow,
    DayCount,
    RoleOption,
} from '@/types/admin';
import { adminAuth } from './fixtures';

/**
 * Example numbers for the admin page stories. Every figure, rider, and email here is made up. The days end today,
 * so the charts and "last backup" read the way they would on the live page.
 */

const now = new Date();
const generatedAt = now.toISOString();

function isoDay(daysAgo: number): string {
    const date = new Date(now);
    date.setUTCDate(date.getUTCDate() - daysAgo);

    return date.toISOString().slice(0, 10);
}

function isoAgo(minutes: number): string {
    return new Date(now.getTime() - minutes * 60_000).toISOString();
}

/** A steady, made-up wobble, so the same story draws the same chart every time. */
function wobble(index: number, seed: number): number {
    return (
        (Math.sin(index * 1.7 + seed) + Math.sin(index * 0.45 + seed * 2)) / 2
    );
}

/** A count for each of the last `length` days, oldest first, around `base`, busier at the weekend. */
function series(length: number, base: number, seed: number): DayCount[] {
    return Array.from({ length }, (_, index) => {
        const date = isoDay(length - 1 - index);
        const weekend = [0, 6].includes(
            new Date(`${date}T00:00:00Z`).getUTCDay(),
        );
        const growth = 0.75 + (0.25 * index) / length;
        const value =
            base *
            growth *
            (1 + 0.35 * wobble(index, seed)) *
            (weekend ? 1.3 : 1);

        return { date, count: Math.max(0, Math.round(value)) };
    });
}

function total(days: DayCount[]): number {
    return days.reduce((sum, day) => sum + day.count, 0);
}

const usageMetrics: [string, string, number][] = [
    ['route_previews', 'Routes drawn', 410],
    ['route_builds', 'Loops built', 96],
    ['multisport_previews', 'Multisport routes drawn', 14],
    ['point_snaps', 'Points placed', 620],
    ['place_names', 'Places named', 380],
    ['place_searches', 'Place searches', 150],
    ['route_imports', 'Files imported', 9],
    ['routes_saved', 'Routes saved', 29],
    ['fit_exports', 'FIT files exported', 41],
    ['route_sends', 'Routes sent to apps', 12],
    ['shared_downloads', 'Shared link downloads', 18],
    ['device_syncs', 'Watch syncs', 230],
    ['device_downloads', 'Courses sent to watches', 33],
    ['assistant_requests', 'AI assistant requests', 22],
    ['mcp_plan_loop', 'Assistant tool: plan a loop', 11],
    ['mcp_plan_multisport', 'Assistant tool: plan multisport', 2],
    ['mcp_list_routes', 'Assistant tool: list routes', 5],
    ['mcp_route_drawing', 'Assistant tool: route map shown in chat', 9],
    ['sign_ins', 'Sign-ins', 64],
];

const signups = series(90, 4, 1);
const saved = series(90, 26, 2);

/** What AdminOverviewQuery returns: 90 days of riders and routes, 30 days of use. */
export const overview: AdminOverview = {
    generated_at: generatedAt,
    days: 90,
    totals: {
        riders: 1284,
        verified: 1167,
        two_factor: 212,
        new_week: 38,
        new_month: 141,
        active_day: 96,
        active_week: 412,
        active_month: 803,
        online_now: 7,
        saved_routes: 5932,
        routes_month: 864,
        watches: 318,
        watches_active: 241,
        assistant_riders: 57,
        service_accounts: 3,
    },
    signups,
    saved,
    active: series(90, 90, 3).map((day, index) => ({
        date: day.date,
        day: day.count,
        week: Math.round(300 + index * 1.4 + 20 * wobble(index, 4)),
        month: Math.round(620 + index * 2.1 + 15 * wobble(index, 5)),
    })),
    usage: usageMetrics.map(([metric, label, base], index) => {
        const days = series(30, base, index + 10);

        return { metric, label, total: total(days), days };
    }),
    sports: [
        { label: 'Road', count: 2140 },
        { label: 'Gravel', count: 1318 },
        { label: 'Run', count: 1066 },
        { label: 'Mountain bike', count: 512 },
        { label: 'Trail run', count: 431 },
        { label: 'Walk', count: 289 },
        { label: 'Hike', count: 176 },
    ],
    sends: [
        { label: 'Wahoo', count: 264 },
        { label: 'Ride with GPS', count: 139 },
    ],
    assistants: [
        { label: 'Claude', count: 41 },
        { label: 'Example assistant', count: 16 },
    ],
};

const requestDays = series(30, 41_000, 6);
const visitDays = series(30, 520, 7);

/** What TrafficQuery returns once Cloudflare answered: 30 days of requests and consented visits. */
export const traffic: AdminTraffic = {
    connected: true,
    error: null,
    days: 30,
    generated_at: generatedAt,
    totals: {
        requests: total(requestDays),
        cached: Math.round(total(requestDays) * 0.62),
        bytes: 48_300_000_000,
        threats: 1_842,
        page_loads: Math.round(total(visitDays) * 2.4),
        visits: total(visitDays),
    },
    request_days: requestDays.map((day, index) => ({
        date: day.date,
        requests: day.count,
        cached: Math.round(day.count * 0.62),
        bytes: day.count * 39_000,
        threats: 40 + Math.round(30 * Math.abs(wobble(index, 8))),
    })),
    visit_days: visitDays.map((day) => ({
        date: day.date,
        page_loads: Math.round(day.count * 2.4),
        visits: day.count,
    })),
    top_pages: [
        { label: '/', count: 9_420 },
        { label: '/docs', count: 2_210 },
        { label: '/garmin', count: 1_388 },
        { label: '/status', count: 604 },
        { label: '/privacy', count: 212 },
    ],
    top_referrers: [
        { label: 'google.com', count: 4_102 },
        { label: 'duckduckgo.com', count: 1_266 },
        { label: 'apps.garmin.com', count: 948 },
        { label: 'reddit.com', count: 512 },
        { label: 'bing.com', count: 233 },
    ],
    countries: [
        { label: 'Belgium', count: 6_840 },
        { label: 'Netherlands', count: 3_118 },
        { label: 'Germany', count: 1_502 },
        { label: 'United Kingdom', count: 1_090 },
        { label: 'France', count: 744 },
    ],
};

const parts = { connected: true, error: null, generated_at: generatedAt };

/** What the Requests tab gets: 72 hours, a year of days, and how requests were answered. */
export const requests: AdminRequests = {
    ...parts,
    days: 30,
    year_days: 365,
    hours: Array.from({ length: 72 }, (_, index) => {
        const time = new Date(now);
        time.setUTCMinutes(0, 0, 0);
        time.setUTCHours(time.getUTCHours() - (71 - index));
        const daytime = Math.sin(((time.getUTCHours() - 6) / 24) * Math.PI * 2);

        return {
            time: time.toISOString(),
            requests: Math.round(
                1_700 + 900 * daytime + 200 * wobble(index, 9),
            ),
            threats: Math.max(0, Math.round(2 + 2 * wobble(index, 10))),
        };
    }),
    year: series(365, 30_000, 11).map((day, index) => ({
        date: day.date,
        requests: day.count,
        threats: 30 + Math.round(25 * Math.abs(wobble(index, 12))),
    })),
    statuses: [
        { status: 200, count: 1_021_400 },
        { status: 304, count: 146_200 },
        { status: 302, count: 38_900 },
        { status: 404, count: 12_740 },
        { status: 403, count: 6_310 },
        { status: 429, count: 820 },
        { status: 419, count: 96 },
        { status: 500, count: 14 },
    ],
    content_types: [
        { label: 'html', count: 402_100 },
        { label: 'json', count: 351_600 },
        { label: 'js', count: 198_300 },
        { label: 'css', count: 74_900 },
        { label: 'png', count: 61_200 },
        { label: 'woff2', count: 38_400 },
    ],
    browsers: [
        { label: 'Chrome', count: 41_200 },
        { label: 'Safari', count: 23_800 },
        { label: 'Firefox', count: 6_410 },
        { label: 'Edge', count: 3_920 },
        { label: 'Samsung Internet', count: 1_180 },
    ],
    http_versions: [
        { label: 'HTTP/2', count: 812_000 },
        { label: 'HTTP/3', count: 344_000 },
        { label: 'HTTP/1.1', count: 70_900 },
    ],
    ip_classes: [
        { label: 'noRecord', count: 1_109_000 },
        { label: 'searchEngine', count: 61_400 },
        { label: 'monitoringService', count: 21_600 },
        { label: 'badHost', count: 9_840 },
        { label: 'tor', count: 380 },
    ],
};

const blockDays = series(30, 210, 13);
const threatDays = series(30, 60, 14);

/** What the Security tab gets: firewall blocks, threats, and the sign-up check. */
export const security: AdminSecurity = {
    ...parts,
    days: 30,
    turnstile_days: 7,
    event_limit: 10_000,
    threat_days: threatDays,
    threat_countries: [
        { label: 'US', count: 612 },
        { label: 'CN', count: 418 },
        { label: 'RU', count: 233 },
        { label: 'NL', count: 140 },
        { label: 'T1', count: 52 },
    ],
    block_days: blockDays,
    blocks: total(blockDays),
    events: 4_812,
    sources: [
        { label: 'firewallCustom', count: 3_104 },
        { label: 'firewallManaged', count: 1_022 },
        { label: 'ratelimit', count: 498 },
        { label: 'bic', count: 188 },
    ],
    actions: [
        { label: 'block', count: 4_120 },
        { label: 'managed_challenge', count: 692 },
    ],
    countries: [
        { label: 'US', count: 1_840 },
        { label: 'DE', count: 812 },
        { label: 'CN', count: 640 },
        { label: 'SG', count: 402 },
        { label: 'BE', count: 61 },
    ],
    paths: [
        { label: '/wp-login.php', count: 1_412 },
        { label: '/.env', count: 980 },
        { label: '/xmlrpc.php', count: 611 },
        { label: '/.git/config', count: 402 },
        { label: '/register', count: 18 },
    ],
    user_agents: [
        { label: 'python-requests/2.32', count: 1_210 },
        { label: 'Go-http-client/1.1', count: 884 },
        { label: 'curl/8.7', count: 402 },
        { label: 'Mozilla/5.0 (compatible; ExampleScanner/1.0)', count: 260 },
    ],
    rules: [
        { label: 'Block scanners on PHP paths', count: 2_031 },
        { label: 'Block dotfiles', count: 1_382 },
        { label: 'Rate limit sign-in', count: 498 },
    ],
    people_pages: [
        {
            path: '/register',
            method: 'POST',
            rule: 'Rate limit sign-in',
            source: 'ratelimit',
            count: 12,
        },
        {
            path: '/login',
            method: 'POST',
            rule: 'Rate limit sign-in',
            source: 'ratelimit',
            count: 6,
        },
    ],
    people_blocked: 18,
    turnstile: [
        { label: 'challenge_issued', count: 312 },
        { label: 'challenge_non_interactive_solved', count: 271 },
        { label: 'challenge_interactive_solved', count: 24 },
        { label: 'challenge_non_interactive_siteverify_solved', count: 268 },
        { label: 'challenge_failed', count: 9 },
    ],
};

/** What the AI and bots tab gets: verified crawlers, reads of the AI files, impostors, and probes. */
export const bots: AdminBots = {
    ...parts,
    days: 30,
    verified: [
        { label: 'Googlebot', count: 18_420 },
        { label: 'Bingbot', count: 6_310 },
        { label: 'ClaudeBot', count: 2_204 },
        { label: 'Applebot', count: 1_380 },
        { label: 'DuckDuckBot', count: 612 },
    ],
    verified_total: 28_926,
    reads: [
        { path: '/robots.txt', method: 'GET', status: 200, count: 4_120 },
        { path: '/llms.txt', method: 'GET', status: 200, count: 1_204 },
        { path: '/index.md', method: 'GET', status: 200, count: 610 },
        {
            path: '/.well-known/oauth-authorization-server',
            method: 'GET',
            status: 200,
            count: 288,
        },
        { path: '/mcp', method: 'POST', status: 401, count: 142 },
    ],
    reads_total: 6_364,
    readers: [
        { label: 'ClaudeBot', count: 1_920 },
        { label: 'Googlebot', count: 1_544 },
        { label: 'Claude-User', count: 802 },
        { label: 'Bingbot', count: 611 },
    ],
    impostors: [
        { label: 'Googlebot', count: 1_102 },
        { label: 'Bingbot', count: 240 },
        { label: 'facebookexternalhit', count: 96 },
    ],
    impostors_total: 1_438,
    impostors_blocked: 1_290,
    probes: [
        { status: 404, count: 2_810 },
        { status: 403, count: 1_402 },
    ],
    probes_total: 4_212,
    exposed: [],
};

/** What the Speed tab gets: a week of Core Web Vitals and load times, by page. */
export const speed: AdminSpeed = {
    ...parts,
    days: 7,
    vitals: [
        {
            path: '/',
            loads: 2_310,
            lcp: { value: 1_480, rating: 'good', label: 'Good' },
            inp: { value: 96, rating: 'good', label: 'Good' },
            cls: { value: 0.02, rating: 'good', label: 'Good' },
        },
        {
            path: '/dashboard',
            loads: 1_204,
            lcp: { value: 2_910, rating: 'needs_work', label: 'Needs work' },
            inp: { value: 184, rating: 'good', label: 'Good' },
            cls: { value: 0.04, rating: 'good', label: 'Good' },
        },
        {
            path: '/saved',
            loads: 418,
            lcp: { value: 2_240, rating: 'good', label: 'Good' },
            inp: { value: 312, rating: 'needs_work', label: 'Needs work' },
            cls: { value: 0.11, rating: 'needs_work', label: 'Needs work' },
        },
        {
            path: '/docs',
            loads: 96,
            lcp: { value: 4_620, rating: 'poor', label: 'Poor' },
            inp: null,
            cls: { value: 0.01, rating: 'good', label: 'Good' },
        },
    ],
    timings: [
        { path: '/', loads: 2_310, load_ms: 1_920, first_paint_ms: 640 },
        {
            path: '/dashboard',
            loads: 1_204,
            load_ms: 3_410,
            first_paint_ms: 880,
        },
        { path: '/saved', loads: 418, load_ms: 2_680, first_paint_ms: 720 },
        { path: '/docs', loads: 96, load_ms: null, first_paint_ms: 1_120 },
    ],
};

const writeDays = series(30, 290, 15);

/** What the Backups tab gets: Litestream writing to R2, the last file today. */
export const backups: AdminBackups = {
    ...parts,
    days: 30,
    bucket: 'traceway-backups',
    objects: 1_846,
    bytes: 2_140_000_000,
    write_days: writeDays,
    writes: total(writeDays),
    last_write: isoDay(0),
};

/** The roles AdminUsersController offers, from the Role enum. */
export const roles: RoleOption[] = [
    { value: 'super-admin', label: 'Super Admin' },
    { value: 'admin', label: 'Admin' },
    { value: 'user', label: 'User' },
];

const riders: [
    string,
    string,
    string,
    boolean,
    boolean,
    number,
    number,
    number,
][] = [
    // name, email, role, verified, two-factor, routes, days since joining, minutes since last seen
    [
        adminAuth.user.name,
        adminAuth.user.email,
        'super-admin',
        true,
        true,
        48,
        410,
        3,
    ],
    ['Jo Peeters', 'jo.peeters@example.com', 'admin', true, true, 112, 380, 45],
    ['Lotte Janssens', 'lotte@example.com', 'user', true, false, 23, 200, 180],
    ['Mehdi Haddad', 'mehdi.h@example.com', 'user', true, false, 7, 96, 1_500],
    [
        'Anna Novak',
        'anna.novak@example.com',
        'user',
        true,
        true,
        61,
        150,
        4_300,
    ],
    ['Pieter de Vries', 'pieter@example.com', 'user', false, false, 0, 1, 20],
    [
        'Chloé Martin',
        'chloe.martin@example.com',
        'user',
        true,
        false,
        15,
        64,
        9_800,
    ],
    ['Ravi Patel', 'ravi@example.com', 'user', true, false, 34, 260, 22_000],
    ['Femke Wouters', 'femke@example.com', 'user', true, false, 2, 12, 2_900],
    ['Tom Claes', 'tom.claes@example.com', 'user', false, false, 0, 3, 4_200],
    ['Sam Rivera', 'sam.rivera@example.com', 'user', true, false, 19, 48, 600],
];

/** The first page of riders, as AdminUsersQuery returns it, 25 to a page. */
export const userPage: AdminUserPage = {
    users: riders
        .map(
            (
                [name, email, role, verified, twoFactor, routes, joined, seen],
                index,
            ): AdminUserRow => ({
                id: index + 1,
                name,
                email,
                role,
                verified,
                two_factor: twoFactor,
                routes,
                created_at: isoAgo(joined * 24 * 60),
                last_seen_at: isoAgo(seen),
            }),
        )
        // Newest riders first, as the list says.
        .sort((a, b) => (b.created_at ?? '').localeCompare(a.created_at ?? '')),
    page: 1,
    pages: 52,
    total: 1_284,
    per_page: 25,
};

export const noFilters = {
    search: '',
    role: null,
    verified: '',
    two_factor: '',
};

/** One rider's account, as AdminUserQuery finds it: a watch, an assistant, two connected services, two browsers. */
export const lotte: AdminUser = {
    id: 3,
    name: 'Lotte Janssens',
    email: 'lotte@example.com',
    role: 'user',
    email_verified_at: isoAgo(200 * 24 * 60),
    two_factor: false,
    passkeys: 1,
    created_at: isoAgo(200 * 24 * 60),
    updated_at: isoAgo(12 * 24 * 60),
    last_seen_at: isoAgo(180),
    routes: 23,
    watches: [
        {
            label: 'Forerunner 265',
            provider: 'garmin',
            linked_at: isoAgo(150 * 24 * 60),
            last_used_at: isoAgo(600),
        },
    ],
    apps: [{ name: 'Claude', connected_at: isoAgo(40 * 24 * 60) }],
    services: [
        {
            service: 'Google',
            connected_at: isoAgo(200 * 24 * 60),
            last_signed_in_at: isoAgo(180),
            last_sent_at: null,
        },
        {
            service: 'Wahoo',
            connected_at: isoAgo(30 * 24 * 60),
            last_signed_in_at: null,
            last_sent_at: isoAgo(3 * 24 * 60),
        },
    ],
    sessions: [
        { browser: 'Safari on iPhone', last_active_at: isoAgo(180) },
        { browser: 'Firefox on Windows', last_active_at: isoAgo(5 * 24 * 60) },
    ],
};

const alex = { id: 1, name: adminAuth.user.name };
const jo = { id: 2, name: 'Jo Peeters' };

function entry(
    id: number,
    summary: string,
    action: string,
    minutes: number,
    actor: ActivityEntry['actor'],
    rider: ActivityEntry['rider'],
): ActivityEntry {
    return { id, action, summary, at: isoAgo(minutes), actor, rider };
}

const lotteRef = { id: 3, name: 'Lotte Janssens' };

/** What admins did to Lotte's account, newest first. */
export const lotteActivity: ActivityEntry[] = [
    entry(41, 'Signed out everywhere', 'signed_out', 2_900, jo, lotteRef),
    entry(37, 'Changed the name or email', 'updated', 17_000, alex, lotteRef),
];

/** The activity log's first page, as AdminActivityQuery returns it, 30 to a page. */
export const activityPage: AdminActivityPage = {
    entries: [
        entry(
            44,
            'Started acting as the rider',
            'impersonation_started',
            25,
            alex,
            { id: 6, name: 'Pieter de Vries' },
        ),
        entry(
            45,
            'Stopped acting as the rider',
            'impersonation_stopped',
            18,
            alex,
            { id: 6, name: 'Pieter de Vries' },
        ),
        entry(
            43,
            'Marked the email as confirmed',
            'confirmed_email',
            30,
            alex,
            { id: 6, name: 'Pieter de Vries' },
        ),
        entry(
            42,
            'Turned two-factor sign-in off',
            'two_factor_reset',
            1_400,
            jo,
            { id: 4, name: 'Mehdi Haddad' },
        ),
        ...lotteActivity,
        entry(
            36,
            'Changed the role from User to Admin',
            'role_changed',
            30_000,
            alex,
            jo,
        ),
        entry(35, 'Deleted the account', 'deleted', 44_000, jo, null),
        entry(34, 'Opened the account', 'created', 60_000, alex, {
            id: 9,
            name: 'Femke Wouters',
        }),
        entry(33, 'Signed out everywhere', 'signed_out', 90_000, null, {
            id: 8,
            name: 'Ravi Patel',
        }),
    ].sort((a, b) => (b.at ?? '').localeCompare(a.at ?? '')),
    page: 1,
    pages: 2,
    total: 38,
};
