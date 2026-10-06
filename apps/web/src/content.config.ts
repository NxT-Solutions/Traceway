import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const notes = defineCollection({
    loader: glob({ pattern: '**/*.md', base: './src/content/notes' }),
    schema: z.object({
        title: z.string(),
        description: z.string(),
        published: z.coerce.date(),
    }),
});

export const collections = { notes };
