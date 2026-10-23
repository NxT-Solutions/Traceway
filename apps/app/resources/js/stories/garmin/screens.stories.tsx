import type { Meta, StoryObj } from '@storybook/react-vite';

/**
 * The Connect IQ app (`apps/garmin`) draws its screens in Monkey C, which does not run in a browser, so these are the
 * screens as the simulator draws them: an Edge 1040 (282 × 470) and a Forerunner 965 (454 × 454, round). They are the
 * Connect IQ Store images in `apps/garmin/store/screens`.
 *
 * To change a screen, design it here first if you like, then change the Monkey C view (`RoutesView`, `RouteDetail`,
 * `PairView`) and take the capture again as `apps/garmin/store/screens/README.md` explains. A new capture shows up
 * here on the next build.
 */
const meta = {
    title: 'Garmin/Screens',
    parameters: { layout: 'centered', a11y: { test: 'todo' } },
    args: { shape: 'edge', src: '', label: '' },
    argTypes: {
        shape: { control: 'inline-radio', options: ['edge', 'round'] },
    },
    render: ({ shape, src, label }) => (
        <figure className="m-0 flex flex-col items-center gap-3 font-sans text-sm text-foreground">
            <div
                className="overflow-hidden bg-black p-3 shadow-lg"
                style={{
                    borderRadius: shape === 'round' ? '50%' : 28,
                    border: '6px solid #2b2f31',
                }}
            >
                <img
                    src={src}
                    alt={label}
                    width={shape === 'round' ? 454 : 282}
                    height={shape === 'round' ? 454 : 470}
                    style={{
                        display: 'block',
                        borderRadius: shape === 'round' ? '50%' : 8,
                    }}
                />
            </div>
            <figcaption className="max-w-sm text-center text-muted-foreground">
                {label}
            </figcaption>
        </figure>
    ),
} satisfies Meta<{ shape: 'edge' | 'round'; src: string; label: string }>;

export default meta;

type Story = StoryObj<typeof meta>;

export const EdgeRoutes: Story = {
    args: {
        shape: 'edge',
        src: 'garmin/screens/1-edge-routes.png',
        label: 'Edge 1040: your saved routes, with distance and climb. A route on the device says Saved, and the three dots open Options to refresh or unlink.',
    },
};

export const EdgeRouteMap: Story = {
    args: {
        shape: 'edge',
        src: 'garmin/screens/2-edge-route-map.jpg',
        label: 'Edge 1040: a road loop on the map, with Ride it and Save.',
    },
};

export const EdgeLink: Story = {
    args: {
        shape: 'edge',
        src: 'garmin/screens/3-edge-link.png',
        label: 'Edge 1040: the code that links the device on traceway.online.',
    },
};

export const ForerunnerRoutes: Story = {
    args: {
        shape: 'round',
        src: 'garmin/screens/4-forerunner-routes.png',
        label: 'Forerunner 965: the route list on a round watch, with a saved route on top. Options is the last row.',
    },
};

export const ForerunnerRunIt: Story = {
    args: {
        shape: 'round',
        src: 'garmin/screens/5-forerunner-run-it.jpg',
        label: 'Forerunner 965: a trail run on the map, with Run it and Save.',
    },
};
