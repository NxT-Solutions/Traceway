// Published, Storybook lives at traceway.online/storybook/, on the same site as the planner and the content site,
// so a story would share their browser storage: the cookie answer, Paper or Ink, a planner draft. Under
// /storybook/ every story frame gets storage of its own instead: localStorage and sessionStorage keys get a prefix,
// and listing them shows only the frame's own; a cookie is kept to /storybook/. A visitor's real choices on the site
// are never changed by a story. The site's XSRF-TOKEN cookie is hidden too, so a request a story's mocks miss
// fails Laravel's CSRF check. In `storybook dev` and the tests, on their own address, nothing changes.
// apps/app/.storybook has the same file.

// The static build renders the site's stories on the server, where there is no window, and it loads this file with
// the preview: without the check it would fail, and the stories would lose their stylesheets.
if (typeof window !== 'undefined' && window.location.pathname.startsWith('/storybook/')) {
    // Each frame its own: a docs page shows its stories side by side, each in a frame of its own, all at once, and
    // one story's cookie answer or draft must not reach the story next to it. The keys go when the frame does, so
    // they never pile up beside the planner's own.
    const frame = new URLSearchParams(window.location.search).get('id') ?? 'story';
    const prefix = `storybook:${frame}:`;
    const storage = Storage.prototype;
    const { getItem, setItem, removeItem, key } = storage;
    const length = Object.getOwnPropertyDescriptor(storage, 'length')?.get;
    const own = (store: Storage): string[] => {
        const names: string[] = [];
        const count = length ? (length.call(store) as number) : 0;

        for (let index = 0; index < count; index += 1) {
            const name = key.call(store, index);

            if (name?.startsWith(prefix)) {
                names.push(name.slice(prefix.length));
            }
        }

        return names;
    };

    storage.getItem = function (name: string) {
        return getItem.call(this, prefix + name);
    };
    storage.setItem = function (name: string, value: string) {
        setItem.call(this, prefix + name, value);
    };
    storage.removeItem = function (name: string) {
        removeItem.call(this, prefix + name);
    };
    storage.key = function (index: number) {
        return own(this)[index] ?? null;
    };
    storage.clear = function () {
        for (const name of own(this)) {
            removeItem.call(this, prefix + name);
        }
    };
    Object.defineProperty(storage, 'length', {
        configurable: true,
        get(this: Storage) {
            return own(this).length;
        },
    });
    window.addEventListener('pagehide', (event) => {
        // Kept while the browser holds the page to come back to it.
        if (event.persisted) {
            return;
        }

        try {
            for (const store of [window.localStorage, window.sessionStorage]) {
                for (const name of own(store)) {
                    removeItem.call(store, prefix + name);
                }
            }
        } catch {
            // Storage is blocked. Then nothing was kept.
        }
    });

    const cookie = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');

    if (cookie?.get && cookie.set) {
        const { get, set } = cookie;

        Object.defineProperty(document, 'cookie', {
            configurable: true,
            get() {
                return (get.call(document) as string)
                    .split('; ')
                    .filter((pair) => !pair.startsWith('XSRF-TOKEN='))
                    .join('; ');
            },
            set(value: string) {
                set.call(
                    document,
                    `${value.replace(/;\s*path=[^;]*/i, '')}; path=/storybook/`,
                );
            },
        });
    }
}

export {};
