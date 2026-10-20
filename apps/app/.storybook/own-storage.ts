// Published, Storybook lives at traceway.online/storybook/, on the same site as the planner and the content site,
// so a story would share their browser storage: the cookie answer, Paper or Ink, a planner draft. Under
// /storybook/ every story gets storage of its own instead: localStorage and sessionStorage keys get a prefix, and
// listing them shows only the story's own; a cookie is kept to /storybook/. A visitor's real choices on the site
// are never changed by a story. The site's XSRF-TOKEN cookie is hidden too, so a request a story's mocks miss
// fails Laravel's CSRF check. In `storybook dev` and the tests, on their own address, nothing changes.
// apps/web/.storybook has the same file.

if (window.location.pathname.startsWith('/storybook/')) {
    const prefix = 'storybook:';
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
