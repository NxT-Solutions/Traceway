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
