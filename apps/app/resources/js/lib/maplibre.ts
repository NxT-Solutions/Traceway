import { setWorkerUrl } from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import type { ResolvedAppearance } from '@/hooks/use-appearance';

// Vite emits the worker as its own hashed asset. Without this call MapLibre
// asks for maplibre-gl-worker.mjs next to its own chunk, which a production
// build does not have, and every map on the page stays blank. Each page that
// draws a map imports this module before it builds one.
setWorkerUrl(maplibreWorkerUrl);

export const streetsMapStyle = 'https://tiles.openfreemap.org/styles/liberty';
export const lightMapStyle = 'https://tiles.openfreemap.org/styles/positron';
export const darkMapStyle = 'https://tiles.openfreemap.org/styles/dark';

/**
 * The basemap that matches the appearance: streets on paper, the dark scene at night.
 */
export function themedMapStyle(appearance: ResolvedAppearance): string {
    return appearance === 'dark' ? darkMapStyle : streetsMapStyle;
}
