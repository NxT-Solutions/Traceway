import { delay, HttpResponse, http } from 'msw';
// Types `context.msw`, so a story can `beforeEach({ msw }) { msw.use(…) }`.
import type {} from 'msw-storybook-addon/csf3';
import type { FlashToast } from '@/types/ui';
import { sharedProps } from './inertia-page';

type Props = Record<string, unknown>;

/**
 * What a page answer can carry besides its props: its address, the shared props, the flash (a toast), the request
 * it answers, and the props the controller defers (`Inertia::defer`). With the request, a partial reload
 * (`only: […]`, or the fetch of deferred props) gets only the props it asked for, as Laravel sends them.
 */
export type PageOptions = {
    url?: string;
    shared?: Props;
    flash?: { toast?: FlashToast } & Props;
    request?: Request;
    deferred?: string[];
};

/** The props a partial reload of `component` asked for, or null when the request is a full visit. */
function askedFor(component: string, request?: Request): Set<string> | null {
    const asked = request?.headers.get('X-Inertia-Partial-Data');

    if (
        !asked ||
        request?.headers.get('X-Inertia-Partial-Component') !== component
    ) {
        return null;
    }

    // Inertia's middleware always sends the errors.
    return new Set([...asked.split(','), 'errors']);
}

function pick(props: Props, keep: (key: string) => boolean): Props {
    return Object.fromEntries(
        Object.entries(props).filter(([key]) => keep(key)),
    );
}

/**
 * What Laravel answers an Inertia visit with: the page as JSON with the `X-Inertia` header. Inertia then swaps the
 * page in place, so a story's form, link or button can lead to the next page with no server behind it.
 */
export function inertiaPage(
    component: string,
    props: Props,
    {
        url = '/',
        shared = sharedProps(),
        flash = {},
        request,
        deferred = [],
    }: PageOptions = {},
) {
    const all = { ...shared, ...props };
    const asked = askedFor(component, request);
    // A full visit leaves the deferred props out and names them, so Inertia fetches them right after.
    const first = asked === null && deferred.length > 0;

    return HttpResponse.json(
        {
            component,
            props:
                asked !== null
                    ? pick(all, (key) => asked.has(key))
                    : pick(all, (key) => !deferred.includes(key)),
            ...(first ? { deferredProps: { default: deferred } } : {}),
            url,
            version: null,
            clearHistory: false,
            encryptHistory: false,
            flash,
        },
        { headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' } },
    );
}

/**
 * A form that fails validation: Laravel sends the rider back to the same page with the errors, which Inertia shows
 * next to the fields. Errors in a named bag, such as Fortify's `confirmTwoFactorAuthentication`, nest under it.
 */
export function validationErrors(
    component: string,
    props: Props,
    errors: Record<string, string | Record<string, string>>,
    options: PageOptions = {},
) {
    const shared = options.shared ?? sharedProps();

    return inertiaPage(component, props, {
        ...options,
        shared: { ...shared, errors },
    });
}

/**
 * What Laravel answers a form with when it is done: `redirect()->route(…)` or `back()`, a 302 to the next page (a 303
 * after a PUT, PATCH or DELETE, which Inertia's middleware sets). The browser follows it with a GET, which the story
 * answers with `inertiaPage`.
 */
export function redirect(to: string, status: 302 | 303 = 302) {
    return new HttpResponse(null, { status, headers: { Location: to } });
}

/**
 * What Laravel answers a visit that must leave Inertia with, such as a download or a page outside the app
 * (`Inertia::location`): a 409 with the address, which Inertia opens as a full page load.
 */
export function location(to: string) {
    return new HttpResponse(null, {
        status: 409,
        headers: { 'X-Inertia-Location': to },
    });
}

/**
 * What a form sent, whether Inertia sent it as JSON (the default) or as form data (with a file), so a story can
 * record it with a `fn()` spy and its play function can check the server got what the rider typed.
 */
export async function sentData(
    request: Request,
): Promise<Record<string, unknown>> {
    const type = request.headers.get('Content-Type') ?? '';

    if (type.includes('application/json')) {
        return (await request.clone().json()) as Record<string, unknown>;
    }

    if (type.includes('form')) {
        return Object.fromEntries((await request.clone().formData()).entries());
    }

    return {};
}

/**
 * A Wayfinder route as a Mock Service Worker path: `/settings/devices/{deviceLink}` becomes
 * `/settings/devices/:deviceLink`, so one handler answers every id and reads it from `params`.
 */
export function pathOf(route: { definition: { url: string } }) {
    return route.definition.url.replace(/\{(\w+)\??\}/g, ':$1');
}

/**
 * A handler for what a page's `<Form {...route.form()}>` sends. Wayfinder spoofs PUT, PATCH and DELETE the way
 * Laravel reads them, as a POST to `…?_method=PATCH`, so the handler answers that POST; the query is not part of
 * the match. `redirect(…, 303)` is then what Laravel answers, as Inertia's middleware sees the spoofed method.
 */
export function form(
    definition: { action: string; method: string },
    resolver: Parameters<typeof http.post>[1],
) {
    const path = new URL(definition.action, window.location.origin).pathname;

    return definition.method === 'get'
        ? http.get(path, resolver)
        : http.post(path, resolver);
}

/** Waits like a real request would, so a story shows its busy state before the answer. */
export const network = () => delay(400);

export { delay, HttpResponse, http };
