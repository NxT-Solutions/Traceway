// Published, Storybook lives at traceway.online/storybook/, on the same site as the planner and the content site,
// so a story would share their browser storage: the cookie answer, Paper or Ink, a planner draft. Under
// /storybook/ every story gets storage of its own instead: localStorage and sessionStorage keys get a prefix, and
// a cookie is kept to /storybook/. A visitor's real choices on the site are never read or changed by a story. In
// `storybook dev` and the tests, on their own address, nothing changes. apps/web/.storybook has the same file.

if (window.location.pathname.startsWith('/storybook/')) {
    const prefix = 'storybook:';
    const storage = Storage.prototype;
    const { getItem, setItem, removeItem, key } = storage;

    storage.getItem = function (name: string) {
        return getItem.call(this, prefix + name);
    };
    storage.setItem = function (name: string, value: string) {
        setItem.call(this, prefix + name, value);
    };
    storage.removeItem = function (name: string) {
        removeItem.call(this, prefix + name);
    };
    storage.clear = function () {
        for (let index = this.length - 1; index >= 0; index -= 1) {
            const name = key.call(this, index);

            if (name?.startsWith(prefix)) {
                removeItem.call(this, name);
            }
        }
    };

    const cookie = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');

    if (cookie?.get && cookie.set) {
        const { get, set } = cookie;

        Object.defineProperty(document, 'cookie', {
            configurable: true,
            get() {
                return get.call(document);
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
