# Traceway application

How WCAG 2.2 lands on this site. This file is the behavior to build.
It is not a statement that the site conforms. Evidence would live in
`docs/evidence/` later. There is no VoiceOver claim.

## Stack

- The content site is Astro (`apps/web`). The planner, auth, and
  settings are the Laravel app (`apps/app`).
- Both stylesheets share the tokens in `hig/traceway.md`:
  `apps/web/src/styles/global.css` and
  `apps/app/resources/css/app.css`.
- Outfit is self-hosted at 400, 500, and 600. No font CDN.
- Motion is 180ms, press 80ms. `prefers-reduced-motion: reduce` snaps.
  A snap is not a loss of the status text.

## Text and non-text

| Pair | Rule |
| --- | --- |
| `--ink` on `--paper` | body text, ≥4.5:1 |
| `--ink` on `--card` | text on cards, ≥4.5:1 |
| `--muted` on `--paper` or `--card` | secondary text that carries meaning, ≥4.5:1 |
| `--chartreuse-ink` on `--chartreuse` | the filled label, ≥4.5:1 |
| Focus ring against the adjacent surface | ≥3:1 |

Do not put body copy in chartreuse on paper. Chartreuse is the filled
control and small status, and the status word is still there (1.4.1).

Hairlines use `--hairline`. They group. They are not the focus
indicator.

## Focus

`:focus-visible` is a 2px ring, offset from the control, at least 3:1
against the adjacent color (1.4.11, 2.4.7). Do not set `outline: none`
without that ring.

Keyboard order follows the visual order (2.4.3). The map is not a
trap (2.1.2).

## Targets and names

Interactive targets are at least 24×24 CSS pixels (2.5.8), including
the planner bar, Draw route, header links, and auth submits. Status
pills are text, not targets.

The accessible name contains the visible label (2.5.3). Do not hide
“Draw route” or “Open the planner” behind a different name.

## Errors

Errors render under the field, in text, associated with that field
(`aria-describedby` or `aria-errormessage`), with `role="alert"`
(3.3.1, 3.3.2). A red border alone is not the error. The label stays.

## Draw status

The planner exposes one live region for draw status (`role="status"`).
It announces drawing, ready, and failure without moving focus (4.1.3).

The Draw route button keeps its label when busy and shows a spinner on
that control. The spinner is `aria-hidden`. The live region carries
the sentence. Reduced motion removes the spin and leaves the label and
the status text.

## Keyboard

Home, Log in, Planner, and Settings are fully reachable by keyboard
(2.1.1). Filters, via, the loop switch, Draw route, GPX, and FIT do
not depend on pointer-only hits. Escape leaves a layer that covers
the page.

## What this file will not say

It will not say the site conforms to WCAG 2.2 A, AA, or AAA. It will
not say VoiceOver, or any other assistive technology, was tested.
Those sentences wait on `docs/evidence/`, which this repo does not
treat as written.
