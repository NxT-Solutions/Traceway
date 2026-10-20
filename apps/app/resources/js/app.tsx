import { createInertiaApp } from '@inertiajs/react';
import ImpersonationBar from '@/components/impersonation-bar';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { pageLayout } from '@/lib/page-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Traceway';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: pageLayout,
    strictMode: true,
    withApp(app, { page }) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <ImpersonationBar initial={page} />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#C6E84A',
    },
});

// This will set light / dark mode on load...
initializeTheme();
