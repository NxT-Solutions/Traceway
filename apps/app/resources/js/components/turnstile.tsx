import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type TurnstileApi = {
    render: (
        element: HTMLElement,
        options: {
            sitekey: string;
            theme: 'light';
            size: 'flexible';
            'response-field-name': string;
        },
    ) => string;
    reset: (widget: string) => void;
    remove: (widget: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const script =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<TurnstileApi> | null = null;

// The script loads once, on the first form that needs it, and only there.
function loadTurnstile(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    loading ??= new Promise((resolve, reject) => {
        const tag = document.createElement('script');
        tag.src = script;
        tag.async = true;
        tag.onload = () =>
            window.turnstile
                ? resolve(window.turnstile)
                : reject(new Error('Turnstile did not start.'));
        tag.onerror = () => {
            loading = null;
            reject(new Error('Turnstile did not load.'));
        };
        document.head.appendChild(tag);
    });

    return loading;
}

/**
 * Cloudflare's check that a person fills the form. Most people see it tick
 * by itself. It puts its token in the form as cf-turnstile-response.
 * A token works once, so the check starts again after the form comes back
 * with an error.
 */
export default function Turnstile({
    siteKey,
    error,
    attempt,
    className,
}: {
    siteKey: string | null;
    error?: string;
    attempt: unknown;
    className?: string;
}) {
    const box = useRef<HTMLDivElement>(null);
    const widget = useRef<string | null>(null);

    useEffect(() => {
        if (!siteKey || !box.current) {
            return;
        }

        const element = box.current;
        let gone = false;

        loadTurnstile()
            .then((turnstile) => {
                if (gone) {
                    return;
                }

                widget.current = turnstile.render(element, {
                    sitekey: siteKey,
                    // The sign-in pages are always paper, in the dark theme too.
                    theme: 'light',
                    size: 'flexible',
                    'response-field-name': 'cf-turnstile-response',
                });
            })
            .catch(() => {
                // The server says so when the token is missing.
            });

        return () => {
            gone = true;

            if (widget.current && window.turnstile) {
                window.turnstile.remove(widget.current);
            }

            widget.current = null;
        };
    }, [siteKey]);

    useEffect(() => {
        if (attempt && widget.current && window.turnstile) {
            window.turnstile.reset(widget.current);
        }
    }, [attempt]);

    if (!siteKey) {
        return null;
    }

    return (
        <div className={cn('grid gap-2', className)}>
            <div ref={box} className="min-h-[65px]" />
            <InputError message={error} />
        </div>
    );
}
