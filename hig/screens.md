# Surfaces

Four surfaces share one visual system. The content site lives in
`apps/web`. Log in, the planner, and settings live in `apps/app`.

## Shared header

One header on the content site, on auth, and on settings:

- the mark (one folded path)
- the word Traceway
- How it works
- Sports
- Devices
- Log in, or You when someone is signed in

On Home and the other content pages, the one filled control in that
header is “Open the planner”. Those links are text. They are not a row
of filled buttons.

The planner does not stack a second filled “Open the planner” on the
map. You are already there.

Auth does not put a second fill in the header. The form submit is the
filled control on that view. “Open the planner” in the auth header is
a text link.

## Home — `apps/web/src/pages/index.astro`

Purpose: say what Traceway does, then open the planner.

The content site is this one page. How it works, Sports, Multisport,
Devices, and FAQ are sections on it, not pages of their own.

- The hero is one idea: draw a loop, export a course file.
- Copy stays short and physical. No club, no score, no forbidden words.
- One filled control: “Open the planner”.
- The mark is the folded path, next to the word.

## Log in — `apps/app/resources/js/pages/auth/`

Purpose: get a guest into the planner and back to the route.

- `login.tsx` — Log in
- `register.tsx` — create the account
- `forgot-password.tsx` and `reset-password.tsx` — reset
- `verify-email.tsx` — verify

Confirm-password and two-factor use this same layout. They do not
invent a new chrome.

- The shared header is present. The filled control is the form submit,
  one per view: Log in, Create account, Send reset, Reset password, or
  Resend.
- Fields are labeled. Errors sit under the field.
- A guest who opens `/dashboard` is sent to log in and, after success,
  returned to the planner. The return is the point of the form.

## Planner — `apps/app/resources/js/pages/dashboard.tsx`

Purpose: draw the loop. Route `GET /dashboard`.

- The map is the content. It fills the view.
- One bar holds the filters, via, the loop switch, GPX, and FIT.
- “Draw route” is the only filled button.
- Filters, via, the loop switch, GPX, and FIT are not filled and are
  not pills. Status may be a pill. The action is a rounded rectangle.
- Busy keeps the words “Draw route” and shows a spinner on that button.
- GPX and FIT export a course file. They do not open a second app.

## Settings — `apps/app/resources/js/pages/settings/`

Purpose: account details for the person who draws routes.

- `profile.tsx`, `security.tsx`, `appearance.tsx`.
- The shared header stays (You, not a new brand).
- Grouped sections, with short labels. This is not a second sidebar
  product and not a second navigation brand.
- At most one filled commit control on the view. Destructive actions
  stay outlined or text.

## Shared defects to refuse

- Two filled buttons in one view
- A planner toolbar of equal filled pills
- Settings drawn as its own sidebar product
- A guest stuck on log in with no return to the planner
- Strava orange, the chevron mark, or the words beacon, kudos,
  segment, or suffer
- A mark that is not the single folded path
- Hover that scales or casts a shadow
- A busy control whose label disappears
