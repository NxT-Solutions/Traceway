import type { Meta, StoryObj } from '@storybook/react-vite';

/**
 * The bitmaps the Connect IQ app draws with (`apps/garmin/resources/drawables`, listed in `drawables.xml`) and the
 * art on its store listing (`apps/garmin/store`). Sizes on the watch are a share of the screen width: sport marks
 * 12%, the wordmark 48%. A saved route's sport mark carries a check.
 */
const meta = {
    title: 'Garmin/Artwork',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

function Tile({
    src,
    label,
    dark,
}: {
    src: string;
    label: string;
    dark?: boolean;
}) {
    return (
        <figure className="m-0 flex flex-col items-center gap-2 font-sans text-xs text-foreground">
            <div
                className="flex size-28 items-center justify-center rounded-lg border border-border p-3"
                style={{ background: dark ? '#000000' : '#f7f5ec' }}
            >
                <img src={src} alt="" className="max-h-full max-w-full" />
            </div>
            <figcaption>{label}</figcaption>
        </figure>
    );
}

export const Drawables: Story = {
    render: () => (
        <div className="flex flex-wrap gap-4">
            {[
                ['sport_road', 'Road'],
                ['sport_road_saved', 'Road, saved'],
                ['sport_mtb', 'Mountain bike'],
                ['sport_mtb_saved', 'Mountain bike, saved'],
                ['sport_foot', 'Foot'],
                ['sport_foot_saved', 'Foot, saved'],
                ['check', 'Check'],
                ['start_dot', 'Start dot'],
                ['launcher_icon', 'Launcher icon'],
            ].map(([file = '', label = '']) => (
                <Tile
                    key={file}
                    src={`garmin/drawables/${file}.svg`}
                    label={label}
                    dark
                />
            ))}
            <Tile src="garmin/drawables/wordmark.svg" label="Wordmark" dark />
            <Tile
                src="garmin/drawables/wordmark_black.svg"
                label="Wordmark, black"
            />
        </div>
    ),
};

export const StoreListing: Story = {
    render: () => (
        <div className="flex flex-col gap-4 font-sans text-sm text-foreground">
            <img
                src="garmin/hero.svg"
                alt="Traceway's store banner: the mark, the name and a loop on an ink map"
                className="w-full max-w-3xl rounded-lg border border-border"
            />
            <div className="flex gap-4">
                <Tile src="garmin/icon.svg" label="Store cover, 500 × 500" />
                <Tile
                    src="garmin/device-icon-64.svg"
                    label="64-colour device icon"
                />
            </div>
        </div>
    ),
};
