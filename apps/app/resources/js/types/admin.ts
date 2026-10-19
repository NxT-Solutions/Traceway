export type Day = { date: string; count: number };
export type Ranked = { label: string; count: number };

export type AdminOverview = {
    generated_at: string;
    days: number;
    totals: {
        riders: number;
        verified: number;
        two_factor: number;
        new_week: number;
        new_month: number;
        active_day: number;
        active_week: number;
        active_month: number;
        online_now: number;
        saved_routes: number;
        routes_month: number;
        watches: number;
        watches_active: number;
        assistant_riders: number;
        service_accounts: number;
    };
    signups: Day[];
    saved: Day[];
    active: { date: string; day: number; week: number; month: number }[];
    usage: { metric: string; label: string; total: number; days: Day[] }[];
    sports: Ranked[];
    sends: Ranked[];
    assistants: Ranked[];
};

export type AdminTraffic = {
    connected: boolean;
    error: string | null;
    days: number;
    generated_at: string;
    totals: {
        requests: number;
        cached: number;
        bytes: number;
        threats: number;
        page_loads: number;
        visits: number;
    };
    request_days: {
        date: string;
        requests: number;
        cached: number;
        bytes: number;
        threats: number;
    }[];
    visit_days: { date: string; page_loads: number; visits: number }[];
    top_pages: Ranked[];
    top_referrers: Ranked[];
    countries: Ranked[];
};

export type RoleOption = { value: string; label: string };

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    verified: boolean;
    two_factor: boolean;
    routes: number;
    created_at: string | null;
    last_seen_at: string | null;
};

export type AdminUserPage = {
    users: AdminUserRow[];
    page: number;
    pages: number;
    total: number;
    per_page: number;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    email_verified_at: string | null;
    two_factor: boolean;
    passkeys: number;
    created_at: string | null;
    updated_at: string | null;
    last_seen_at: string | null;
    routes: number;
    watches: {
        label: string | null;
        provider: string;
        linked_at: string | null;
        last_used_at: string | null;
    }[];
    apps: { name: string; connected_at: string | null }[];
    services: {
        service: string;
        connected_at: string | null;
        last_sent_at: string | null;
    }[];
    sessions: { browser: string; last_active_at: string }[];
};

export type ActivityEntry = {
    id: number;
    action: string;
    summary: string;
    at: string | null;
    actor: { id: number; name: string } | null;
    rider: { id: number; name: string } | null;
};

export type AdminActivityPage = {
    entries: ActivityEntry[];
    page: number;
    pages: number;
    total: number;
};

export type StatusCount = { status: number; count: number };
export type DayCount = { date: string; count: number };

type InsightsPart = {
    connected: boolean;
    error: string | null;
    generated_at: string;
};

export type AdminRequests = InsightsPart & {
    days: number;
    year_days: number;
    hours: { time: string; requests: number; threats: number }[];
    year: { date: string; requests: number; threats: number }[];
    statuses: StatusCount[];
    content_types: Ranked[];
    browsers: Ranked[];
    http_versions: Ranked[];
    ip_classes: Ranked[];
};

export type AdminSecurity = InsightsPart & {
    days: number;
    turnstile_days: number;
    event_limit: number;
    threat_days: DayCount[];
    threat_countries: Ranked[];
    block_days: DayCount[];
    blocks: number;
    events: number;
    sources: Ranked[];
    actions: Ranked[];
    countries: Ranked[];
    paths: Ranked[];
    user_agents: Ranked[];
    rules: Ranked[];
    people_pages: Ranked[];
    turnstile: Ranked[];
};

export type AdminBots = InsightsPart & {
    days: number;
    verified: Ranked[];
    verified_total: number;
    reads: { path: string; method: string; status: number; count: number }[];
    reads_total: number;
    readers: Ranked[];
    impostors: Ranked[];
    impostors_total: number;
    impostors_blocked: number;
    probes: StatusCount[];
    probes_total: number;
    exposed: Ranked[];
};

export type Vital = {
    value: number;
    rating: 'good' | 'needs_work' | 'poor';
    label: string;
} | null;

export type AdminSpeed = InsightsPart & {
    days: number;
    vitals: {
        path: string;
        loads: number;
        lcp: Vital;
        inp: Vital;
        cls: Vital;
    }[];
    timings: {
        path: string;
        loads: number;
        load_ms: number | null;
        first_paint_ms: number | null;
    }[];
};

export type AdminBackups = InsightsPart & {
    days: number;
    bucket: string | null;
    objects: number;
    bytes: number;
    write_days: DayCount[];
    writes: number;
    last_write: string | null;
};
