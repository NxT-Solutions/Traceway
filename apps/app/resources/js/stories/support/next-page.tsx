import { usePage } from '@inertiajs/react';
import { pageLayout } from '@/lib/page-layout';

/**
 * A plain stand-in for the page a flow ends on when the story does not draw it, such as the planner after logging
 * in. It says which page Inertia got, so a play function can check the flow got there.
 */
export function NextPage() {
    const { component, url } = usePage();
    // A page with its own layout already sits in a landmark; a bare one needs its own.
    const Wrapper = pageLayout(component) ? 'div' : 'main';

    return (
        <Wrapper className="flex min-h-svh flex-col items-center justify-center gap-2 p-6 text-center">
            <h1 className="text-2xl font-semibold">Next page: {component}</h1>
            <p className="text-muted-foreground">
                Inertia went to <code>{url}</code>. The story stops here.
            </p>
        </Wrapper>
    );
}
