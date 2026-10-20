import LastUsed from '@/components/sign-in-last-used';
import SignInLink from '@/components/sign-in-link';
import { signIn } from '@/routes/connections';
import type { ConnectionSlug, SignInOption } from '@/types';

type Props = {
    option: SignInOption;
    lastUsed: boolean;
    /** "Remember me" was ticked, so the rider stays logged in after the service sends them back. */
    remember?: boolean;
    /** Show "Continue with" on screen, not only to a screen reader. */
    spelled?: boolean;
};

/**
 * Google asks that its button says "Continue with Google" on screen, never the name alone.
 */
export function namesItsAction(slug: ConnectionSlug): boolean {
    return slug === 'google';
}

/**
 * One service on the log in pages. It reads "Continue with Google" to everyone. In the grid of every service,
 * the others show only their name on screen, and "Continue with" stays for screen readers.
 */
export default function SignInTile({
    option,
    lastUsed,
    remember = false,
    spelled = false,
}: Props) {
    const shown = spelled || namesItsAction(option.slug);

    return (
        <SignInLink
            slug={option.slug}
            href={signIn.url(
                option.slug,
                remember ? { query: { remember: 1 } } : undefined,
            )}
            className="provider"
        >
            <span className="name">
                <span>
                    {shown ? (
                        'Continue with '
                    ) : (
                        <span className="vh">Continue with </span>
                    )}
                    {option.label}
                </span>
                {option.note ? (
                    <small className="note">
                        <span className="vh">, </span>
                        {option.note}
                    </small>
                ) : null}
            </span>
            {lastUsed ? <LastUsed /> : null}
        </SignInLink>
    );
}
