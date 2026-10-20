import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import SignInLegal from '@/components/sign-in-legal';
import SignInTile from '@/components/sign-in-tile';
import { options as moreOptions } from '@/routes/login';
import type { SignInOptions } from '@/types';

type Props = {
    options: SignInOptions;
    /** "Remember me" on the log in form is ticked. */
    remember?: boolean;
    intent?: 'login' | 'register';
};

/**
 * The services riders log in with most, the one this browser used last first, and a way to the rest. Each is a
 * full-width row that says "Continue with" the service. Nothing when this Traceway has no service set up, so
 * the page is the email form alone.
 */
export default function SignInOptionsGrid({
    options,
    remember = false,
    intent = 'login',
}: Props) {
    if (options.featured.length === 0) {
        return null;
    }

    // The rest of the services keep "Remember me", and say sign up when the rider came from there.
    const query = {
        ...(remember ? { remember: 1 } : {}),
        ...(intent === 'register' ? { intent: 'register' } : {}),
    };

    return (
        <div className="sign-in-options">
            <ul className="providers single">
                {options.featured.map((option) => (
                    <li key={option.slug}>
                        <SignInTile
                            option={option}
                            lastUsed={options.lastUsed === option.slug}
                            remember={remember}
                            spelled
                        />
                    </li>
                ))}
            </ul>
            {options.more > 0 ? (
                <Link
                    href={moreOptions(
                        Object.keys(query).length > 0 ? { query } : undefined,
                    )}
                    className="outline more"
                >
                    {intent === 'register'
                        ? 'More ways to sign up'
                        : 'More ways to log in'}
                    <span className="count" aria-hidden="true">
                        +{options.more}
                    </span>
                    <span className="vh">, {options.more} more</span>
                    <ChevronRight size={18} aria-hidden="true" />
                </Link>
            ) : null}
            <SignInLegal intent={intent} />
        </div>
    );
}
