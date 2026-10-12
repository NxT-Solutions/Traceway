import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const contentNav = [
    { title: 'How it works', href: '/#how' },
    { title: 'Sports', href: '/#sports' },
    { title: 'Devices', href: '/#devices' },
] as const;

const textLinkClass =
    'inline-flex min-h-6 items-center text-sm text-foreground no-underline transition-colors duration-[var(--motion)] ease-[var(--ease)] motion-reduce:transition-none hover:text-foreground/80';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const [menuOpen, setMenuOpen] = useState(false);
    const user = auth.user;
    const onDashboard = isCurrentUrl(dashboard());

    const closeMenu = () => setMenuOpen(false);

    return (
        <>
            <header className="border-b border-border">
                <div className="mx-auto flex w-full max-w-6xl items-center gap-4 px-4 py-4 md:px-6">
                    <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 shrink-0 lg:hidden"
                                aria-label="Menu"
                            >
                                <Menu />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="bg-background">
                            <SheetHeader>
                                <SheetTitle className="sr-only">
                                    Menu
                                </SheetTitle>
                            </SheetHeader>
                            <nav
                                className="flex flex-col gap-4 px-2"
                                aria-label="Primary"
                            >
                                {contentNav.map((item) => (
                                    <a
                                        key={item.href}
                                        href={item.href}
                                        className={textLinkClass}
                                        onClick={closeMenu}
                                    >
                                        {item.title}
                                    </a>
                                ))}
                                {user ? (
                                    <Link
                                        href={editProfile()}
                                        className={textLinkClass}
                                        onClick={closeMenu}
                                    >
                                        You
                                    </Link>
                                ) : (
                                    <a
                                        href="/login"
                                        className={textLinkClass}
                                        onClick={closeMenu}
                                    >
                                        Log in
                                    </a>
                                )}
                                {!onDashboard && (
                                    <Button asChild className="mt-2 w-fit">
                                        <Link
                                            href={dashboard()}
                                            onClick={closeMenu}
                                        >
                                            Open the planner
                                        </Link>
                                    </Button>
                                )}
                            </nav>
                        </SheetContent>
                    </Sheet>

                    <a href="/" className="inline-flex shrink-0">
                        <AppLogo />
                    </a>

                    <nav
                        className="hidden items-center gap-4 lg:flex"
                        aria-label="Primary"
                    >
                        {contentNav.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                className={textLinkClass}
                            >
                                {item.title}
                            </a>
                        ))}
                    </nav>

                    <div className="ml-auto hidden items-center gap-4 lg:flex">
                        {user ? (
                            <Link
                                href={editProfile()}
                                className={textLinkClass}
                            >
                                You
                            </Link>
                        ) : (
                            <a href="/login" className={textLinkClass}>
                                Log in
                            </a>
                        )}
                        {!onDashboard && (
                            <Button asChild>
                                <Link href={dashboard()}>Open the planner</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </header>
            {breadcrumbs.length > 0 && (
                <div className="border-b border-border">
                    <div className="mx-auto flex w-full max-w-6xl items-center px-4 py-4 text-muted-foreground md:px-6">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
