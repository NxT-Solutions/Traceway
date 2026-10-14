import type { Auth } from '@/types/auth';
import type { WhatsNew } from '@/types/releases';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            whatsNew?: WhatsNew | null;
            [key: string]: unknown;
        };
    }
}
