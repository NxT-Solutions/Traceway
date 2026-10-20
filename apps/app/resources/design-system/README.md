Traceway plans loop routes for any sport and sends them to a watch, a GPS or a bike computer. The map is the page; the panel beside it says what you asked and what you got. Build every screen from these rules.

## Voice

Riders are often 60 and over and just want a route. Write for them.

- Short, plain, physical sentences. "Name the distance. Get a loop worth taking." "Three moves. Then you go."
- Second person, present tense. "Drop a pin, pick your sport and a distance."
- Name things the way a rider does: start, distance, pass through, surface, climbing, your watch. Never costing, waypoint, polyline, payload.
- Foot and bike are equal. Run, trail run, walk, hike, road, gravel and mountain bike get the same weight in copy and in controls.
- Numbers with units in words: "41.6 km", "180 m up", "Loop 1 of 3". Kilometres by default.
- Sentence case everywhere. Uppercase only in the `rail` style (11px, 0.09em tracking).
- No emoji. No exclamation marks. Buttons say what happens ("Send to device"), and the toast says what did ("Sent to your Edge 540").
- Never use Strava orange (#FC4C02), their wordmark, the chevron mark, or the words beacon, kudos, segment or suffer.

## Colour

Two appearances share one set of bones: **Paper** (light, `paper` ground, `ink` text) and **Ink** (dark, the blue-green night of the map scene). The marker, `chart`, is the only accent.

- `chart` fills the one forward action on a screen (`tw-fill`, `tw-new`, Button `default` via `primary`) with `chart-ink` text, and draws the search frontier on the map. Hover is `chart-dim`.
- Never set text in `chart` or `primary` on paper (about 1.8:1). On Ink it reads (9.4:1).
- The route line is never the marker: it is `route`, ink on paper and paper on ink, over a `halo`. Loops not picked are `loser` at 25% (Paper) or 45% (Ink).
- Two token layers, one palette:
  - **Planner scene** (planner, library, map): `bg`, `text`, `planner-muted`, `hair`, `panel`, `panel-b`, `field`, `field-b`, `outline`, `hover`, `seg-on`, `seg-on-fg`, `icon-box`.
  - **shadcn layer** (sign-in, settings, admin): `background`, `foreground`, `card`, `popover`, `primary`, `secondary`, `muted`, `muted-foreground`, `accent`, `border`, `input`, `ring`, `destructive`, `sidebar-*`.
  - Don't mix layers inside one component.
- Map colours are the `m-*` tokens: land `m-base`, woods `m-wood`, homes `m-res`, buildings `m-bld`, water `m-water`, roads `m-minor` and `m-major` with their casings on Paper only.
- States carry a word or an icon as well as a colour: `warning` with TriangleAlert, `down` with the word Down, `destructive` for deletes and invalid fields.
- Neutrals lean warm on Paper (hue 95) and blue-green on Ink (hue 200). No pure greys.

## Type

One family: **Outfit** at 400, 500, 600 and 700 (`fonts/`), stack `Outfit, 'Avenir Next', 'Segoe UI', system-ui, sans-serif`.

- Every number is `tabular-nums`: distance, ascent, time, counts. The planner sets it on the whole scene.
- Display styles (`display-hero`, `display-section`, `display-narration`, `display-tile`) are 600 or 500 with tight negative tracking and leading near 1. They clamp down on phones.
- Planner text: `route-title` 20/600, `stat` 32/600 for the distance, `body` 15/400, `control` 15/500, `label` 14/500, `section-label` 13/500 in `planner-muted`, `caption` 13/400.
- Headings are 600. Body is 400. Nothing lighter than 400.
- Keep running text near 60 characters (`lede` paragraphs are `max-width: 60ch`).

## Space, radius, depth

- Spacing follows a 4px base with half steps the planner really uses: `space-1` 4, `space-1.5` 6, `space-2` 8, `space-2.5` 10, `space-3` 12, `space-3.5` 14, `space-4` 16, `space-5` 20, `space-6` 24.
- Controls are `radius-lg` (10px). Inner options sit concentric at `radius-seg` (7px, 4px in). Tiles `radius-tile` 12, the panel `radius-panel` 16, padded cards `radius-card` 30, chips `radius-pill`.
- Heights: header 64, primary fill 48, outline and fields 42, option groups 34, map buttons 40, ghost icon 36, stepper 30. Touch targets are 44px where a rider taps on the move (nav, account).
- Surfaces are flat and divided by `hair` rules. Only things that float over the map get `shadow-panel`: the panel, popovers, map controls, toasts.

## Motion and states

- Duration 180ms on `cubic-bezier(0.22, 1, 0.36, 1)` for colour and border changes. Press: opacity .72 in 80ms. No scaling, no bounce.
- `prefers-reduced-motion` sets the duration to 0ms and turns transitions off.
- Focus: a solid 2px outline at 2px offset, in `ring` on shadcn screens and `text` in the planner. Never remove it.
- Disabled: opacity .6 (planner) or .8 (Button), and say why in the label when you can.

## Layout

- The planner is a full-height map with the 400px `PlannerPanel` floating 24px from the top-left. Map controls sit bottom-right. On phones the panel becomes a bottom sheet with 16px top corners and safe-area padding.
- The library is a four-column grid of `RouteCard`s, 16px apart, with `LibraryFilters` above.
- The site is one page, short and plain, on the Ink map scene.

## Iconography

- Icons are **lucide-react** at 2px stroke, 16–18px in controls, in `currentColor`. Common ones: Footprints, Bike, Mountain, ChevronRight, Check, CircleAlert, TriangleAlert, LoaderCircle, Copy, ExternalLink.
- `assets/Icons/multisport.svg` is Traceway's own swim-bike-run mark, drawn at lucide's weight. The file strokes in `ink` (#071f20); in code it uses `currentColor`.
- No emoji, no filled icon sets, no illustrations. The map does the illustrating.

## Logo

- The mark is one folded path ending in a chartreuse dot (`marker-dot`). Use `assets/Logos/traceway-mark-ink.svg` on Paper and `traceway-mark-paper.svg` on Ink; `traceway-app-icon.svg` is the favicon tile.
- Lockup: 28px mark, 10px gap, "Traceway" in `brand-name`. See the Logo component.
- Never redraw, recolour the dot, or set the wordmark in another face.
