# Surfaces

The same four surfaces as `hig/screens.md`. Each one has a keyboard
path, a visible focus ring, and text for anything color is also saying.
None of this is a conformance claim. There is no VoiceOver claim.

## Home — `apps/web/src/pages/index.astro`

Purpose: read what Traceway does, then open the planner. The content
site is this one page.

- Keyboard path: mark and word, then How it works, Sports, Devices,
  Log in (or You), then “Open the planner”, then the main
  content in DOM order. Focus order matches that row (2.4.3, 2.1.1).
- The header is a `nav`. The page has one `h1` (1.3.1, 1.3.2).
- The mark is decorative beside the word Traceway (1.1.1). “Open the
  planner” contains those words in its accessible name (2.5.3).
- The filled link and the text links are at least 24px in the hit
  area (2.5.8). Focus ring is visible and at least 3:1 (2.4.7, 1.4.11).
- Nothing on the home page traps Tab (2.1.2).

## Log in — `apps/app/resources/js/pages/auth/`

Purpose: sign in, register, reset, or verify, then return to the
planner. Forgot-password is part of reset. Confirm-password and
two-factor follow the same rules.

- Keyboard path: header links, then each field from top to bottom, then
  the one submit. Tab does not skip a field or leave the person inside
  the form (2.1.1, 2.1.2, 2.4.3).
- Every field has a visible label (3.3.2). The accessible name contains
  that label (2.5.3).
- Errors sit under the field, in text, associated with it, as an alert.
  A border color is not the error (3.3.1, 1.4.1).
- Targets, including the submit, are at least 24px (2.5.8). The focus
  ring is the same 2px ring as the rest of the site (2.4.7, 1.4.11).
- A guest sent here from `/dashboard` returns to the planner after a
  successful log in. The return does not depend on a pointer.

## Planner — `apps/app/resources/js/pages/dashboard.tsx`

Purpose: draw a loop and export the course. The map is the content.

- Keyboard path: the bar, in visual order — filters, via, the loop
  switch, GPX, FIT — and Draw route. The map does not trap Tab.
  Escape closes any layer the bar opened (2.1.1, 2.1.2, 2.4.3).
- Draw route is a `button`. Its accessible name contains “Draw route”.
  GPX and FIT contain those visible words (2.5.3, 4.1.2). The loop
  switch exposes on or off, not only a color (1.4.1, 4.1.2).
- Every control in the bar is at least 24px (2.5.8). Focus is visible
  on the bar and on Draw route (2.4.7, 1.4.11).
- One live region reports draw status: drawing, the loop is ready, or
  drawing failed. Updating it does not move focus (4.1.3). The button
  keeps the label “Draw route” and shows a spinner on itself. The
  spinner is decorative (1.1.1).
- The map has an accessible name. The route picture is not the only
  statement of distance or failure (1.1.1, 1.4.1).
- Field errors in the bar, including via, sit under that field (3.3.1,
  3.3.2).

## Settings — `apps/app/resources/js/pages/settings/`

Purpose: profile, security, and appearance as grouped sections. Not a
second sidebar product.

- Keyboard path: the shared header, then each group in visual order,
  fields before that group’s commit control (2.1.1, 2.4.3). No trap
  (2.1.2).
- Groups are headings or fieldsets. Labels match the visible labels
  (1.3.1, 1.3.2, 3.3.2, 2.5.3).
- Errors sit under the field (3.3.1). Focus ring and 24px targets match
  the rest of the site (2.4.7, 1.4.11, 2.5.8).
- A save confirmation, if any, is a status message and does not steal
  focus (4.1.3).

## Shared defects to refuse

- A control with no keyboard path
- Focus lost inside the map
- An `aria-label` that does not contain the visible text
- An error that is only a colored border, or that sits far from the field
- Draw state that is only a spinner, or a status update that moves focus
- A target smaller than 24px
- `outline: none` without a 3:1 focus ring
- A sentence that claims WCAG conformance or VoiceOver
