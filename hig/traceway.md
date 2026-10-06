# Traceway application

How the HIG lands on this site. The content site and the planner are
one product. If a stylesheet and this file disagree, fix the
stylesheet. Do not fork a second palette.

## Two stylesheets, one contract

These files must use the same token names and the same values:

- `apps/web/src/styles/global.css`
- `apps/app/resources/css/app.css`

A token that exists in only one file is a defect. Bronze zinc is not a
Traceway color.

## Color

| Token | Value | Role |
| --- | --- | --- |
| `--paper` | `oklch(0.97 0.012 95)` | page |
| `--ink` | `oklch(0.22 0.03 200)` | text |
| `--chartreuse` | `oklch(0.78 0.17 128)` | the filled control |
| `--chartreuse-ink` | `oklch(0.2 0.04 200)` | label on chartreuse |
| `--muted` | `oklch(0.45 0.03 200)` | secondary text |
| `--hairline` | `oklch(0.88 0.02 120)` | grouping |
| `--card` | `oklch(0.985 0.008 95)` | opaque cards |

Do not paint chrome with Strava orange `#FC4C02`.

## Type

`--font-sans`: `Outfit`, `ui-sans-serif`, `system-ui`, `sans-serif`.

Outfit is self-hosted at 400, 500, and 600. No font CDN. No remote
`@font-face`. SF Pro is not required and is not the brand font.

| Token | Size | Weight |
| --- | --- | --- |
| `--text-meta` | 11px | 400 or 500 |
| `--text-body` | 13px | 400 |
| `--text-title` | 15px | 500 or 600 |
| `--text-page` | 22px | 500 or 600 |
| `--text-hero` | 26px | 500 or 600 |

`letter-spacing: 0` on body and controls. No Thin, no Light, no 700.

## Space

Only this scale: 4, 8, 12, 16, 20, 24, 32, 40 px.

| Token | Value |
| --- | --- |
| `--space-4` | 4px |
| `--space-8` | 8px |
| `--space-12` | 12px |
| `--space-16` | 16px |
| `--space-20` | 20px |
| `--space-24` | 24px |
| `--space-32` | 32px |
| `--space-40` | 40px |

## Radius

| Token | Value | Use |
| --- | --- | --- |
| `--radius-control` | 10px | fields and buttons |
| `--radius-card` | `10px + card padding` | cards |
| `--radius-pill` | 999px | status only |

## Motion

| Token | Value |
| --- | --- |
| `--duration` | 180ms |
| `--ease` | `cubic-bezier(0.22, 1, 0.36, 1)` |
| `--duration-press` | 80ms |

Nothing runs longer than 200ms. `prefers-reduced-motion: reduce`
snaps: no slide, no fade, no spinner loop. The end state is just
there.

## States

Every control has rest, hover, press, focus-visible, disabled, and
busy.

- **Rest** — opaque fill or hairline. No shadow.
- **Hover** — a color shift only. Do not scale. Do not add a shadow.
- **Press** — 80ms, on the control.
- **Focus-visible** — a visible ring. See `wcag/traceway.md`.
- **Disabled** — still readable. Not the only cue that the action exists.
- **Busy** — the label stays. A spinner sits on that same control.
  The spinner does not replace the words.

## Mark and copy

The mark is one folded path. It is not a wordmark, a letter stack, or
a chevron. Beside it, the name is the word Traceway.

Copy is short and physical: draw a loop, export a course, put the file
on the computer. Do not use the Strava wordmark, the chevron mark, or
the words beacon, kudos, segment, or suffer.

## Provenance

Reimplement from this folder. Do not lift another product’s orange,
marks, or vocabulary, and do not repaint Traceway in Bronze zinc.
