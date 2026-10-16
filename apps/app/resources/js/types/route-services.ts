/** A service Traceway sends routes to, as the server shares it with every page. */
export type RouteServiceSlug = 'wahoo' | 'ride_with_gps';

export type RouteServiceStatus = {
    service: RouteServiceSlug;
    label: string;
    connected: boolean;
    needs_reconnect: boolean;
    connected_at: string | null;
    last_sent_at: string | null;
};
