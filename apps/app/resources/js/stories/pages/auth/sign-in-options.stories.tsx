import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ComponentProps } from 'react';
import SignInOptions from '@/pages/auth/sign-in-options';
import { guestAuth } from '../../support/fixtures';
import { InertiaPage, sharedProps } from '../../support/inertia-page';
import { optionGroups } from './sign-in-fixtures';

type Props = ComponentProps<typeof SignInOptions>;

/**
 * Every service set up on this Traceway, in its group (`/login/options`). It words itself for the page the rider
 * came from: log in or sign up.
 */
const meta = {
    title: 'Pages/Auth/Sign in options',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function page(props: Props) {
    return () => (
        <InertiaPage
            name="auth/sign-in-options"
            component={SignInOptions}
            props={props}
            shared={sharedProps(guestAuth)}
            url={`/login/options?intent=${props.intent}`}
        />
    );
}

/** From the log in page. This browser last used Wahoo. */
export const LogIn: Story = {
    render: page({
        groups: optionGroups,
        lastUsed: 'wahoo',
        remember: false,
        intent: 'login',
    }),
};

export const SignUp: Story = {
    render: page({
        groups: optionGroups,
        lastUsed: null,
        remember: false,
        intent: 'register',
    }),
};
