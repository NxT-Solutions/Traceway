import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    Bike,
    Check,
    ChevronRight,
    CircleAlert,
    Copy,
    ExternalLink,
    Footprints,
    LoaderCircle,
    Mountain,
    TriangleAlert,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { MultisportIcon } from '@/components/multisport-icon';
import favicon from '../../../../public/favicon.svg?url';

/**
 * The mark is one folded path ending in a chartreuse dot (`marker-dot`). Use the files: the ink mark on Paper, the
 * paper mark on Ink, the app icon for the favicon. Never redraw it, recolour the dot, or set the wordmark in another
 * face. Icons are lucide at 2px stroke in `currentColor`; the multisport mark is Traceway's own, at the same weight.
 */
const meta = {
    title: 'Brand',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;

type Story = StoryObj<typeof meta>;

export const Logo: Story = {
    render: () => (
        <div className="flex flex-col gap-6 font-sans text-foreground">
            <AppLogo />
            <div className="flex flex-wrap items-end gap-6">
                <AppLogoIcon className="size-16" />
                <AppLogoIcon className="size-10" />
                <AppLogoIcon className="size-7" />
            </div>
        </div>
    ),
};

export const LogoFiles: Story = {
    render: () => (
        <div className="grid max-w-2xl gap-4 font-sans text-sm sm:grid-cols-3">
            {[
                [
                    'brand/Logos/traceway-mark-ink.svg',
                    'Ink mark, for Paper',
                    '#f7f5ec',
                ],
                [
                    'brand/Logos/traceway-mark-paper.svg',
                    'Paper mark, for Ink',
                    '#071f20',
                ],
                [favicon, 'App icon and favicon', '#e9e7dc'],
            ].map(([src, label, ground]) => (
                <figure key={src} className="m-0 flex flex-col gap-2">
                    <div
                        className="flex aspect-square items-center justify-center rounded-lg border border-border"
                        style={{ background: ground }}
                    >
                        <img src={src} alt="" className="size-24" />
                    </div>
                    <figcaption>{label}</figcaption>
                </figure>
            ))}
        </div>
    ),
};

export const Iconography: Story = {
    render: () => (
        <div className="flex flex-col gap-4 font-sans text-sm text-foreground">
            <div className="flex flex-wrap items-center gap-5">
                <Footprints aria-label="Foot" />
                <Bike aria-label="Bike" />
                <Mountain aria-label="Hills" />
                <MultisportIcon aria-label="Multisport" />
                <ChevronRight aria-label="Open" />
                <Check aria-label="Done" />
                <CircleAlert aria-label="Error" />
                <TriangleAlert aria-label="Warning" />
                <LoaderCircle aria-label="Loading" />
                <Copy aria-label="Copy" />
                <ExternalLink aria-label="Opens elsewhere" />
            </div>
            <p className="m-0 max-w-prose text-muted-foreground">
                lucide-react, 16 to 18px in controls, 2px stroke. No emoji, no
                filled icon sets, no illustrations: the map does the
                illustrating.
            </p>
        </div>
    ),
};
