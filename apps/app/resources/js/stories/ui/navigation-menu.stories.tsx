import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, waitFor } from 'storybook/test';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';

/**
 * The top navigation of the app header: plain links, and a trigger that opens a small panel of related pages.
 */
const meta = {
    title: 'UI/NavigationMenu',
    component: NavigationMenu,
    args: { defaultValue: 'routes', onValueChange: fn() },
    argTypes: {
        value: {
            control: 'select',
            options: ['', 'routes'],
            description:
                'Controlled open item. Leave unset to let it keep its own.',
        },
        defaultValue: {
            control: 'select',
            options: ['', 'routes'],
            description: 'The item open at first; empty for none.',
        },
        orientation: {
            control: 'inline-radio',
            options: ['horizontal', 'vertical'],
            description: 'Which arrow keys move between items.',
            table: { defaultValue: { summary: 'horizontal' } },
        },
        delayDuration: {
            control: { type: 'number', min: 0, step: 50 },
            description:
                'How long a pointer rests on a trigger before it opens, in ms.',
            table: { defaultValue: { summary: '200' } },
        },
        viewport: {
            control: 'boolean',
            description:
                'Draw the open panel in one shared viewport under the list.',
        },
        onValueChange: {
            action: 'value changed',
            description:
                'Called with the open item, or an empty string when it closes.',
        },
    },
    parameters: { docs: { story: { inline: false, iframeHeight: 280 } } },
    render: (args) => (
        <div className="h-60">
            <NavigationMenu {...args}>
                <NavigationMenuList>
                    <NavigationMenuItem>
                        <NavigationMenuLink
                            href="#"
                            className={navigationMenuTriggerStyle()}
                        >
                            Plan
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                    <NavigationMenuItem value="routes">
                        <NavigationMenuTrigger>Routes</NavigationMenuTrigger>
                        <NavigationMenuContent>
                            <ul className="grid w-64 gap-1">
                                <li>
                                    <NavigationMenuLink href="#">
                                        <span className="font-medium">
                                            Saved routes
                                        </span>
                                        <span className="text-muted-foreground">
                                            Every route you kept.
                                        </span>
                                    </NavigationMenuLink>
                                </li>
                                <li>
                                    <NavigationMenuLink href="#">
                                        <span className="font-medium">
                                            Devices
                                        </span>
                                        <span className="text-muted-foreground">
                                            Your Edge 540 and Forerunner 965.
                                        </span>
                                    </NavigationMenuLink>
                                </li>
                            </ul>
                        </NavigationMenuContent>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                        <NavigationMenuLink
                            href="#"
                            className={navigationMenuTriggerStyle()}
                        >
                            What's new
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>
        </div>
    ),
} satisfies Meta<typeof NavigationMenu>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The Routes panel open. */
export const Open: Story = {
    parameters: {
        // Radix NavigationMenu puts focusable, aria-hidden "focus proxy" spans around an open panel to steer Tab into
        // and out of it. axe reports them as aria-hidden-focus; it is upstream and only while a panel is open, so
        // that one rule is off here. Everything else is still checked, and Closed checks the whole menu.
        a11y: {
            config: { rules: [{ id: 'aria-hidden-focus', enabled: false }] },
        },
    },
    play: async ({ canvas }) => {
        await expect(
            canvas.getByRole('button', { name: 'Routes' }),
        ).toHaveAttribute('aria-expanded', 'true');
        await waitFor(() =>
            expect(
                canvas.getByRole('link', { name: /Saved routes/ }),
            ).toBeVisible(),
        );
        await expect(
            canvas.getByRole('link', { name: /Devices/ }),
        ).toBeVisible();
    },
};

/** Closed: Tab to Routes and press Enter to open its panel, then Escape closes it and leaves focus on Routes. */
export const Closed: Story = {
    args: { defaultValue: '' },
    play: async ({ args, canvas, userEvent }) => {
        const trigger = canvas.getByRole('button', { name: 'Routes' });

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await userEvent.tab();
        await expect(canvas.getByRole('link', { name: 'Plan' })).toHaveFocus();
        await userEvent.tab();
        await expect(trigger).toHaveFocus();

        await userEvent.keyboard('{Enter}');
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(args.onValueChange).toHaveBeenLastCalledWith('routes');
        const savedRoutes = await canvas.findByRole('link', {
            name: /Saved routes/,
        });
        await waitFor(() => expect(savedRoutes).toBeVisible());

        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(trigger).toHaveAttribute('aria-expanded', 'false'),
        );
        await expect(args.onValueChange).toHaveBeenLastCalledWith('');
        await expect(trigger).toHaveFocus();
    },
};
