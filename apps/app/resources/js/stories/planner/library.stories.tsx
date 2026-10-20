import type { Meta, StoryObj } from '@storybook/react-vite';
import { Bike, Footprints, Mountain } from 'lucide-react';
import { useState } from 'react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { MapThumb, PlannerScene } from '../support/planner-scene';

type Sport = 'Ride' | 'Run' | 'Hike';

type CardData = {
    date: string;
    sport: Sport;
    name: string;
    specs: string;
};

type LibraryArgs = CardData & {
    /** What the search box holds when the story opens. */
    query: string;
    /** The sport chip pressed when the story opens. */
    chip: 'All' | Sport;
    onOpen: (name: string) => void;
    onFilter: (filter: { query: string; chip: string }) => void;
};

const icons = {
    Ride: <Bike size={16} aria-hidden="true" />,
    Run: <Footprints size={16} aria-hidden="true" />,
    Hike: <Mountain size={16} aria-hidden="true" />,
} satisfies Record<Sport, unknown>;

function Card({
    card,
    onOpen,
}: {
    card: CardData;
    onOpen: (name: string) => void;
}) {
    return (
        <button
            className="card"
            type="button"
            onClick={() => onOpen(card.name)}
        >
            <span className="thumb">
                <MapThumb />
            </span>
            <span className="body">
                <span className="date">{card.date}</span>
                <span className="title-line">
                    <span className="sport-mark">{icons[card.sport]}</span>
                    <span className="title">{card.name}</span>
                </span>
                <span className="specs">{card.specs}</span>
            </span>
        </button>
    );
}

const cards: CardData[] = [
    {
        date: '9 Oct',
        sport: 'Run',
        name: 'Park run, flat',
        specs: '12.0 km · 40 m up · Run',
    },
    {
        date: '4 Oct',
        sport: 'Hike',
        name: 'Kempen hills',
        specs: '18.3 km · 310 m up · Hike',
    },
    {
        date: '28 Sep',
        sport: 'Ride',
        name: 'Gravel to Herselt',
        specs: '63.0 km · 240 m up · Dirt',
    },
];

const chips = ['All', 'Ride', 'Run', 'Hike'] as const;

/** Search and sport chips over the grid; both narrow the cards as the rider types or presses. */
function Library(args: LibraryArgs) {
    const [query, setQuery] = useState(args.query);
    const [chip, setChip] = useState<string>(args.chip);
    const all: CardData[] = [args, ...cards];
    const shown = all.filter(
        (card) =>
            card.name.toLowerCase().includes(query.trim().toLowerCase()) &&
            (chip === 'All' || card.sport === chip),
    );
    const filter = (next: { query?: string; chip?: string }) => {
        const chosen = { query, chip, ...next };
        setQuery(chosen.query);
        setChip(chosen.chip);
        args.onFilter(chosen);
    };

    return (
        <>
            <div className="filters" style={{ maxWidth: 480 }}>
                <input
                    type="search"
                    placeholder="Search your routes"
                    aria-label="Search your routes"
                    value={query}
                    onChange={(event) => filter({ query: event.target.value })}
                />
                <div className="chips" role="group" aria-label="Sport">
                    {chips.map((sport) => (
                        <button
                            key={sport}
                            type="button"
                            aria-pressed={sport === chip}
                            onClick={() => filter({ chip: sport })}
                        >
                            {sport}
                        </button>
                    ))}
                </div>
            </div>
            {shown.length === 0 ? (
                <p className="note">No saved routes match.</p>
            ) : (
                <div className="grid">
                    {shown.map((card) => (
                        <Card
                            key={card.name}
                            card={card}
                            onOpen={args.onOpen}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

/**
 * The saved-routes library, from `planner.css` (`.tw-library`): search and sport chips, then a grid of route cards.
 * Each card shows the route on the map, the date, a sport mark, the name and the specs joined by " · ". Four columns
 * on a desktop, 16px apart. The Controls change the first card and the filters it opens with; the Actions panel
 * logs a card opened and each filter.
 */
const meta = {
    title: 'Planner/Library',
    parameters: { layout: 'fullscreen' },
    args: {
        date: '12 Oct',
        sport: 'Ride',
        name: 'Morning loop',
        specs: '41.6 km · 180 m up · Paved',
        query: '',
        chip: 'All',
        onOpen: fn(),
        onFilter: fn(),
    },
    argTypes: {
        sport: { control: 'inline-radio', options: ['Ride', 'Run', 'Hike'] },
        chip: { control: 'inline-radio', options: [...chips] },
    },
} satisfies Meta<LibraryArgs>;

export default meta;

type Story = StoryObj<LibraryArgs>;

export const RouteCard: Story = {
    render: (args) => (
        <PlannerScene library>
            <div style={{ width: 280 }}>
                <Card card={args} onOpen={args.onOpen} />
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => {
        await userEvent.click(
            within(canvasElement).getByRole('button', { name: /Morning loop/ }),
        );
        await expect(args.onOpen).toHaveBeenCalledWith('Morning loop');
    },
};

/** Search and the sport chips narrow the grid; a filter that matches nothing says so. */
export const Filters: Story = {
    render: (args) => (
        <PlannerScene library>
            <Library key={`${args.query}:${args.chip}`} {...args} />
        </PlannerScene>
    ),
    play: async ({ args, canvasElement, step }) => {
        const canvas = within(canvasElement);
        const titles = () =>
            [...canvasElement.querySelectorAll('.card .title')].map(
                (title) => title.textContent,
            );
        const chip = (name: string) =>
            within(canvas.getByRole('group', { name: 'Sport' })).getByRole(
                'button',
                { name },
            );

        await step('All four routes to begin with', async () => {
            await expect(titles()).toHaveLength(4);
            await expect(chip('All')).toHaveAttribute('aria-pressed', 'true');
        });

        await step('Only the rides', async () => {
            await userEvent.click(chip('Ride'));
            await expect(chip('Ride')).toHaveAttribute('aria-pressed', 'true');
            await expect(titles()).toEqual([
                'Morning loop',
                'Gravel to Herselt',
            ]);
            await expect(args.onFilter).toHaveBeenLastCalledWith({
                query: '',
                chip: 'Ride',
            });
        });

        await step('Search within them', async () => {
            await userEvent.type(
                canvas.getByRole('searchbox', { name: 'Search your routes' }),
                'herselt',
            );
            await expect(titles()).toEqual(['Gravel to Herselt']);
        });

        await step('A hike named Herselt: none', async () => {
            await userEvent.click(chip('Hike'));
            await expect(
                canvas.getByText('No saved routes match.'),
            ).toBeVisible();
        });
    },
};

export const Grid: Story = {
    render: (args) => (
        <PlannerScene library>
            <div className="grid">
                {[args, ...cards].map((card) => (
                    <Card key={card.name} card={card} onOpen={args.onOpen} />
                ))}
            </div>
        </PlannerScene>
    ),
    play: async ({ args, canvasElement }) => {
        await userEvent.click(
            within(canvasElement).getByRole('button', { name: /Kempen hills/ }),
        );
        await expect(args.onOpen).toHaveBeenCalledWith('Kempen hills');
    },
};
