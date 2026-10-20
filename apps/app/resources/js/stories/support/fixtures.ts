import type { AdminAbilities, Auth, User } from '@/types/auth';

/** The rider most stories sign in as. Example data, never a real person. */
export const rider: User = {
    id: 1,
    name: 'Sam Rivera',
    email: 'sam@example.com',
    email_verified_at: '2026-09-01T08:00:00Z',
    two_factor_enabled: false,
    created_at: '2026-09-01T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
};

export const admin: AdminAbilities = {
    admin: true,
    viewAnalytics: true,
    viewUsers: true,
    manageUsers: true,
    assignRoles: true,
};

export const riderAuth: Auth = { user: rider, can: null };
export const adminAuth: Auth = {
    user: { ...rider, name: 'Alex Admin' },
    can: admin,
};
export const guestAuth = { user: null, can: null } as const;
