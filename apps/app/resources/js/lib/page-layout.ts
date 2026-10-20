import AdminLayout from '@/layouts/admin/layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

/**
 * The layout each page renders in. The app and Storybook both use it, so a page story looks like the page.
 */
export function pageLayout(name: string) {
    switch (true) {
        case name === 'welcome':
        case name === 'dashboard':
        case name === 'share':
        case name === 'saved':
        case name === 'status':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return SettingsLayout;
        case name.startsWith('admin/'):
            return AdminLayout;
        default:
            return AppLayout;
    }
}
