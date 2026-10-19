---
name: traceway-pr-check
description: The full check before a Traceway pull request is opened: the gate, the builds, the browser suites, every page at every size and rotation, WCAG, GDPR, and a code review of the branch. Use before opening or updating a PR, or when asked to validate a branch end to end.
---

# Before a pull request

Run all of it, fix what it finds, and run it again until it is clean. Put what ran, and what it found, in the PR description. `traceway-quality` explains each tool; this is the order and the bar.

Commands run from the repo root unless they say otherwise. In a worktree, DDEV sees it at `/var/www/html/.claude/worktrees/<name>`, so pass that path to `ddev exec -d`, not `/var/www/html/apps/app`.

## 1. The gate

```bash
ddev exec -d /var/www/html/apps/app composer ci:check
```

Rector, Pint, PHPArkitect, the Blade formatter, PHPStan, Biome, tsc, and Pest. In a worktree whose `node_modules` came from macOS, Biome and tsc fail inside the container for that reason alone; run `bun run check` and `bun run types:check` from `apps/app` on the host instead. A violation from PHPArkitect is a design problem, not a formatting one: move the class or change the port, do not loosen the rule.

New behaviour needs a Pest test, and a fixed bug needs a test that fails without the fix. Check that it does.

## 2. The builds

```bash
cd apps/app && bun run build:ssr && ls public/build/chat bootstrap/ssr/app.js
cd apps/web && npm run build
```

`public/build/chat` holds MapLibre and Outfit for the chat card. If it is empty, the card in production falls back to the system font and to no map.

`bootstrap/ssr/app.js` draws the planner's pages on the server. Production runs it with `NODE_ENV=production node bootstrap/ssr/app.js` next to PHP; run it the same way for the browser suites, as CI does, so they see the pages the server draws. Without it, Laravel leaves every page to the browser. bun 1.3 cannot parse React's production server build; where host node is broken, `bun bootstrap/ssr/app.js` without `NODE_ENV` runs React's development build, which is fine for the suites.

## 3. The browser suites

On the host, from `apps/app`, with DDEV running and local Valhalla up (`ddev valhalla start`; the public server turns heavy use away):

```bash
bun run test:browser
bun run test:browser:details
bun run test:browser:screens
```

They fail on an uncaught page error, not only on a failed step. A note in the details walk ("the place search named none of the tapped points") is the public Nominatim server, not a failure.

`test:browser:screens` turns every rider page through desktop, tablet both ways, phone both ways, a small phone, and back, on one open page, and fails on sideways scroll. Then **look at the sheets** it leaves (`sheet-<page>.png`, in `$TMPDIR/traceway-screens`, or `SCREENS_DIR`). Look for text that runs into its neighbour, a panel that covers the map, a control under the player bar, a map that kept the old view after the turn. A test cannot see those.

## 4. The chat card

`McpTest` checks that the card's script parses, what it may connect to, and what the model reads. It cannot open a real chat. For a change to `resources/views/mcp/route-map-app.blade.php` or `resources/mcp/route-map.js`:

- Start a new Claude session (chat apps keep the card for a whole session), ask for "a 40 km road loop from Westerlo", and wait 15 seconds.
- On a local site the card prints the chat app's rules and what they blocked, under the card. A change that needs a new domain needs it in `RouteMapApp::appMeta()`.
- A change to `route-map.js` alone reaches open chats without a restart. A change to the Blade card does not.

## 5. WCAG 2.2 AA

`bun run test:browser` runs axe on every page, including the planner with a route's details open and a shared link. Beyond that, for each new piece of UI:

- Text is 4.5:1 and the focus ring 3:1. A new colour token goes into `tests/Unit/Quality/WcagTest.php`, since axe misreads `oklch()`.
- Every control has a name that says what it does to what (`Disconnect Claude`, not three `Disconnect`s), and a focus style.
- Motion has a `prefers-reduced-motion` path that shows the finished state.
- Something that opens looks like it opens. Something that only appears on hover also appears on focus.
- It works at 360 px wide and on a phone on its side.

`composer test -- --group=a11y` is the stylesheet half of this.

## 6. GDPR

`composer test -- --group=gdpr` covers export, deletion, and what a shared course may show. For the branch, also answer each of these, and fix any "no":

- **No new third party sees a visitor.** Fonts, scripts, and styles come from Traceway (`WcagTest` fails on a known CDN). A new outside host a browser talks to (tiles, an API) is listed in the PR, with why.
- **Data sent to a map server is the route, not the person.** Server-side calls go from Traceway's IP, with no account in them.
- **New stored personal data is in the export and goes with the account.** Check `PersonalDataQuery` and `DeleteAccountHandler`; a file on disk is deleted too (see `RouteImageStore::forgetRider`).
- **A public link is signed and kept out of search** (`signed`, `KeepOutOfSearch`), and shows the course, not the rider.
- **An endpoint that fans out to the map servers is capped and rate limited** (`LineWithinReach`, `throttle:map`), so one account cannot get Traceway's IP blocked.
- **Local diagnostics stay local.** Anything that reports or logs extra (the card's report) is behind `app()->isLocal()`.

The privacy policy, the terms, and the cookies page are at `/privacy`, `/terms`, and `/cookies`, from `apps/web/src/legal.ts`. If the branch adds a processor, an outside host, a cookie, or a kind of stored data, update that text in the same branch and bump `legalUpdated`, and say so in the PR.

## 7. Review the branch

```bash
git diff origin/main...HEAD
```

Review it with two fresh reviewers, one for PHP (Domain, Integrations, HTTP, Blade, tests) and one for the frontend (React, CSS, the card's scripts, the Astro site). Ask each for correctness bugs with a concrete failing scenario, security (signed links, ownership, XSS through route, stop, and street names, CORS, uploads), the architecture rules in `AGENTS.md`, and missing tests. Verify each finding in the code before fixing it; drop the ones you cannot back with a scenario.

## 8. Rider notes

Rebase on `main` and compare the branch with it (`git diff origin/main...HEAD --stat`).

- **Something riders use changed:** `apps/app/resources/releases/unreleased/<branch>.md` says what changed for them, all of it, in plain words. Follow the `rider-notes` skill.
- **Nothing for riders:** the pull request gets the `no-rider-notes` label.

The Rider Notes check fails the pull request otherwise.

## 9. Then open the PR

Only with every step clean. In the description: what changed, the checks that ran and their counts, the sheets you looked at, any outside host the branch adds, and what is left for later.
