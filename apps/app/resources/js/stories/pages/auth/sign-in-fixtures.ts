import type { SignInOption, SignInOptionGroup, SignInOptions } from '@/types';

/** The services, as SignInOptionsQuery names them. */
export const services = {
    google: { slug: 'google', label: 'Google' },
    github: { slug: 'github', label: 'GitHub' },
    discord: { slug: 'discord', label: 'Discord' },
    wahoo: { slug: 'wahoo', label: 'Wahoo' },
    rideWithGps: { slug: 'ride_with_gps', label: 'Ride with GPS' },
    runSignup: { slug: 'runsignup', label: 'RunSignup' },
    openStreetMap: { slug: 'openstreetmap', label: 'OpenStreetMap' },
} as const satisfies Record<string, SignInOption>;

/** The log in and sign up pages with every service set up: four up front, three behind "More". */
export const featuredOptions: SignInOptions = {
    featured: [
        services.google,
        services.wahoo,
        services.rideWithGps,
        services.github,
    ],
    more: 3,
    lastUsed: null,
};

/** No service is set up, so the pages offer the email form alone. */
export const noOptions: SignInOptions = {
    featured: [],
    more: 0,
    lastUsed: null,
};

/** "More ways to log in", in the order of ConnectionGroup. */
export const optionGroups: SignInOptionGroup[] = [
    {
        key: 'sport',
        label: 'Sport apps',
        providers: [services.wahoo, services.rideWithGps, services.runSignup],
    },
    {
        key: 'accounts',
        label: 'Everyday accounts',
        providers: [services.google, services.github, services.discord],
    },
    {
        key: 'maps',
        label: 'Maps',
        providers: [services.openStreetMap],
    },
];

/** Password::defaults() in production, as the browser's password manager reads it. */
export const passwordRules =
    'minlength: 12; required: lower; required: upper; required: digit; required: special;';
