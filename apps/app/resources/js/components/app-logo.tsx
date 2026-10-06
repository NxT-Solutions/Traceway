import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <span className="flex items-center gap-2 text-sm font-semibold tracking-tight text-foreground">
            <AppLogoIcon className="size-7" aria-hidden="true" />
            <span>Traceway</span>
        </span>
    );
}
