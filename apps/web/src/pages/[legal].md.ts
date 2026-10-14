import type { APIRoute, GetStaticPaths } from 'astro';
import { type LegalPage, legalPages, markdown } from '../legal';

// Each legal page as Markdown, from the same words the page shows.
export const getStaticPaths = (() =>
    legalPages.map((page) => ({
        params: { legal: page.path.slice(1) },
        props: { page },
    }))) satisfies GetStaticPaths;

export const GET: APIRoute<{ page: LegalPage }> = ({ props, site }) =>
    new Response(markdown(props.page, site), {
        headers: { 'Content-Type': 'text/markdown; charset=utf-8' },
    });
