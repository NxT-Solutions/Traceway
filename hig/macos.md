# Foundations

Source: [HIG](https://developer.apple.com/design/human-interface-guidelines).
Apple writes these for macOS. Traceway is a website. Use the rules
below. Do not invent a menu bar, a window titlebar, or an SF Pro
requirement.

## Typography

The scale is short. Hierarchy comes from size, weight, and color.

| Role | Size | Use |
| --- | --- | --- |
| Meta | 11px | captions, chords, secondary facts |
| Body | 13px | paragraphs, fields, control labels |
| Title | 15px | section titles, card titles |
| Page | 22px | page titles |
| Hero | 26px | the home hero only |

Weights are 400, 500, and 600 only. Do not use Thin, Light, or a
weight below 400. Do not add a second display weight.

Body tracking is 0. Do not track body or controls to look “designed”.
The system font on a Mac is irrelevant here: the site ships Outfit, as
`traceway.md` specifies.

## Layout

Group related items with space, a hairline, or a shared opaque
background. Keep content distinct from controls.

Put the important thing first in reading order. On the planner, that
is the map. Align edges. Do not overlap a full-width field and a
button in one row.

The page may shrink. Critical controls stay reachable; do not pin the
only action to a corner that scrolls away without warning.

## Corners

Nested rounded rectangles share a corner center. Inner radius = outer
radius − padding.

- **Controls** — radius 10px. Rounded rectangles, not capsules.
- **Cards** — radius = 10px + that card’s padding, so a control in the
  corner shares the card’s center.
- **Pills** — status only. Do not pill every button.
- **Grouped rows** — the group has the radius. Rows inside stay square
  so they meet the clip.

Do not pick three unrelated radii for a card, a field, and a button.

## Buttons

A button starts an action now. One filled button per view, for the
most likely action. Other actions are text or an outline.

- Same-size siblings. Distinguish with style, not mismatched heights.
- Press is required. Disabled and busy stay readable.
- Label with a verb. The words are the action: Draw route, Open the
  planner, Log in.
- A destructive action is never the filled button.
- Menus are compact rows, not a second strip of pills.

## Motion

Motion explains a state change. It does not introduce the product.
Frequent controls do not perform on every click.

Duration stays at or under 200ms. Traceway uses 180ms for state, 80ms
for press. `prefers-reduced-motion: reduce` snaps to the end state.
Motion is never the only channel: the label, the status text, or the
error remains.

## Color and materials

Color has a job. Body text sits on an opaque surface. Chartreuse marks
the one filled action and small status, not large fields of page.

Liquid Glass is an Apple system material. Do not fake it with blur,
translucency, or a glassy panel. Opaque fill plus a hairline is the
surface. Do not build hierarchy out of drop shadows.

## Writing

Calm, precise, physical. Cut words. No “we”, no “oops”. An error says
what happened and what to do, next to the field.

Empty states name the next action. Placeholder text shows a format,
not a slogan.
