import type { AstroRenderer } from '@storybook-astro/framework';
import type {
    Args,
    ComponentAnnotations,
    StoryAnnotations,
} from 'storybook/internal/types';

/** A story file's default export, for an Astro component. */
export type Meta<TArgs = Args> = ComponentAnnotations<AstroRenderer, TArgs>;

/** One story of an Astro component. */
export type StoryObj<TArgs = Args> = StoryAnnotations<AstroRenderer, TArgs>;

/** Which page's stylesheets a story gets: an inner page's, the home page's, or the error page's. */
export type Sheets = 'page' | 'home' | 'errors';
