import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import SignInLegal from '@/components/sign-in-legal';
import SignInTile, { namesItsAction } from '@/components/sign-in-tile';
import TextLink from '@/components/text-link';
import { login, register } from '@/routes';
import type { SignInMethod, SignInOption, SignInOptionGroup } from '@/types';

type Props = {
    groups: SignInOptionGroup[];
    lastUsed: SignInMethod | null;
    /** "Remember me" was ticked on the log in page. */
    remember: boolean;
    /** The page the rider came from, so the words and the way back match it. */
    intent: 'login' | 'register';
};

const wordings = {
    login: {
        title: 'More ways to log in',
        description:
            'Pick the account you already use. New to Traceway? It opens your account.',
        back: 'Back to log in',
        email: 'log in with your email',
    },
    register: {
        title: 'More ways to sign up',
        description:
            'Pick the account you already use, and it opens your Traceway account.',
        back: 'Back to sign up',
        email: 'sign up with your email',
    },
};

/** Anything but the sign up page reads as logging in. */
function words(intent: Props['intent'] | undefined) {
    return intent === 'register' ? wordings.register : wordings.login;
}

/**
 * Google and Apple come first in their group. Their buttons span both columns, so the words they ask for fit,
 * and the grid has no gap before them.
 */
function wideFirst(providers: SignInOption[]): SignInOption[] {
    return [
        ...providers.filter((option) => namesItsAction(option.slug)),
        ...providers.filter((option) => !namesItsAction(option.slug)),
    ];
}

export default function SignInOptionsPage({
    groups,
    lastUsed,
    remember,
    intent,
}: Props) {
    const text = words(intent);
    const back = intent === 'register' ? register() : login();

    return (
        <>
            <Head title={text.title} />

            <Link href={back} className="back">
                <ChevronLeft size={18} aria-hidden="true" />
                {text.back}
            </Link>

            {groups.map((group) => (
                <section key={group.key} className="provider-group">
                    <h2>{group.label}</h2>
                    <ul className="providers">
                        {wideFirst(group.providers).map((option) => (
                            <li
                                key={option.slug}
                                className={
                                    namesItsAction(option.slug)
                                        ? 'wide'
                                        : undefined
                                }
                            >
                                <SignInTile
                                    option={option}
                                    lastUsed={lastUsed === option.slug}
                                    remember={remember}
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            ))}

            <SignInLegal intent={intent} />

            <p className="signup">
                Or <TextLink href={back}>{text.email}</TextLink>
            </p>
        </>
    );
}

SignInOptionsPage.layout = ({ intent }: Props) => ({
    title: words(intent).title,
    description: words(intent).description,
});
