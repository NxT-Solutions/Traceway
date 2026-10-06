---
name: wcag
description: Applies WCAG 2.2 to Traceway, a website. Use for any visual change under apps/web or apps/app resources/js or css, including contrast, focus, keyboard, labels, errors, and the planner live region.
---

# WCAG 2.2 — agent workflow

Read this file before any visual, interaction, or copy change that
people perceive or operate. Then read the linked files the change
touches. Do not invent a second accessibility system, and do not claim
conformance.

Official authority: `wcag/README.md`.

## When this applies

- Any visual change under `apps/web`
- Any visual change under `apps/app/resources/js` or
  `apps/app/resources/css`
- New labels, errors, or status text on Home, Log in, Planner, or
  Settings
- A user asks for WCAG, AA, contrast, focus, keyboard, or accessibility
- A screenshot of those four surfaces

## Hard stops (do not violate)

- POUR first: perceivable, operable, understandable, robust.
- Meet the criteria in `criteria.md`. Unlisted criteria are not
  declared N/A.
- Do not claim that the site conforms. Evidence would live in
  `docs/evidence/` later. No VoiceOver claim.
- Semantic elements. Real `button`, `a`, `label`. No clickable `div`.
- Visible `:focus-visible`. The focus ring meets 3:1 against the
  adjacent surface (1.4.11, 2.4.7).
- Text meets 4.5:1 (1.4.3). Status is not color alone (1.4.1).
- The accessible name contains the visible label (2.5.3).
- Targets are at least 24px (2.5.8).
- Every operation has a keyboard path (2.1.1). Nothing traps the
  keyboard (2.1.2). Focus order matches the visual order (2.4.3).
- Errors sit under the field in text (3.3.1, 3.3.2).
- Draw status is a live region and does not move focus (4.1.3).
- Name, role, and value are exposed (4.1.2).
- 4.1.1 Parsing is obsolete in WCAG 2.2. Do not spend a gate on it.

## Workflow — any visual change

1. Name the surface: Home, Log in, Planner, or Settings.
2. Read `screens.md` for that surface and `traceway.md` for focus,
   errors, and the draw-status live region.
3. Map the change to `principles.md` and the rows in `criteria.md`.
4. Keep the HIG hairline. Do not thicken grouping strokes into a new
   look just to chase contrast on non-text decoration. The focus ring
   is the 3:1 obligation spelled out here.
5. Do not flip a public claim. There is nothing to flip.

## Workflow — surface checklist

Copy and tick:

```
Surface: ________
- [ ] 1.1.1 Decorative mark is hidden from AT when the word Traceway is beside it; the map and icons that matter have text
- [ ] 1.3.1 Headings and labels match the visual structure
- [ ] 1.3.2 DOM order is the reading order
- [ ] 1.4.1 Status is not color-only
- [ ] 1.4.3 Text contrast ≥4.5:1
- [ ] 1.4.11 Focus ring ≥3:1 against adjacent colors
- [ ] 1.4.13 Hover or focus tips are dismissible, hoverable, and persistent
- [ ] 2.1.1 A keyboard path reaches every operation
- [ ] 2.1.2 No keyboard trap; Escape leaves an open layer
- [ ] 2.4.3 Focus order matches visual order
- [ ] 2.4.7 Focus is visible
- [ ] 2.5.3 Accessible name contains the visible text
- [ ] 2.5.8 Targets ≥24px
- [ ] 3.3.1 Errors are text under the field
- [ ] 3.3.2 Every field has a label
- [ ] 4.1.2 Name, role, and value are exposed
- [ ] 4.1.3 Draw status uses a live region and does not steal focus
- [ ] Not a conformance claim; no VoiceOver claim
```

## Progressive disclosure

- Principles: [principles.md](principles.md)
- Criteria: [criteria.md](criteria.md)
- Traceway application: [traceway.md](traceway.md)
- Surfaces: [screens.md](screens.md)
- Adapter map: [README.md](README.md)
