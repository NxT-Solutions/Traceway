type Props = {
    /** Under the services on log in or sign up, or above a form that creates the account. */
    intent?: 'login' | 'register' | 'create';
};

const leads = {
    login: 'New to Traceway? Continuing opens your account, and you accept the',
    register: 'Continuing opens your account, and you accept the',
    create: 'By creating an account, you accept the',
};

/**
 * The terms and the privacy policy a new rider accepts. They are pages of the content site, not of the planner,
 * so they open as plain links.
 */
export default function SignInLegal({ intent = 'login' }: Props) {
    return (
        <p className="legal">
            {leads[intent]} <a href="/terms">terms of use</a> and the{' '}
            <a href="/privacy">privacy policy</a>.
        </p>
    );
}
