---
name: storybook
description: Storybook is where Traceway's UI starts, and the source of the Traceway design system in Claude. Use when adding or changing a component, page, token, the content site's look, or a Garmin screen; when asked for a story; or when asked to sync, rebuild, or publish the design system.
---

# Storybook and the design system

Storybook maps the whole product: the planner's React components and pages, the planner scene from `planner.css`, the content site (`apps/web`, its own Storybook composed in under Site (Astro)), and the Garmin app's screens. It is public at https://traceway.online/storybook, published with each release: the release image builds both Storybooks (`.docker/production/images/Dockerfile`) and nginx serves the planner's at `/storybook/app/` and the site's at `/storybook/web/`, with an overview page from the site (`apps/web/src/pages/storybook.astro`) at `/storybook`. `.github/workflows/storybook.yml` builds and tests them on every pull request and on `main`.

Storybook shares the planner's origin, so a signed-in rider's cookies go with a story's requests. Every request a story makes to the planner must be mocked. The preview answers any it misses with a 503 (`noRealServer` in `.storybook/preview.tsx`), and Laravel refuses a change whose referring page is under `/storybook/` (`RefuseStorybookChanges`). Never add a story that needs the real server.

Locally, DDEV serves the same paths from the last builds: https://traceway.ddev.site/storybook (build with `bun run build-storybook` in `apps/app` and `npm run build-storybook` in `apps/web`).

The design system Claude designs with (https://claude.ai/artifact/DaTcu6ZejoYBJWEZRh251m) is built from it. Never edit that design system by hand: change a story, a component, or `resources/design-system/`, then rebuild and sync it.

## Where things are

| What | Where |
| --- | --- |
| Config | `apps/app/.storybook` (`main.ts`, `preview.tsx`, `vite.config.ts`, `manager.ts`) |
| Stories | `apps/app/resources/js/stories/<area>/*.stories.tsx`, docs pages as `*.mdx` |
| Helpers | `stories/support`: `InertiaPage` (a page in its layout with shared props), `PlannerScene` (planner.css classes), `fixtures.ts` (the example rider), `server.ts` (Inertia page, redirect and validation answers for Mock Service Worker), `planner-server.ts` (the planner's whole server from fixtures), `flows.ts` and `story-links.tsx` (flow checks, links between stories) |
| Tests | `apps/app/vitest.config.ts` (projects `storybook` for Paper and `storybook-ink` for Ink), `.storybook/contrast.mjs` (every built page and docs page, both appearances) |
| Tokens and brand book | `apps/app/resources/design-system/` (`tokens.json`, `README.md`, `cover.html`, `assets/`) |
| Design-system build | `apps/app/.storybook/design-system/build.mjs`, run by `bun run design-system` |
| Site Storybook | `apps/web/.storybook` (`@storybook-astro/framework`), stories in `apps/web/src/stories/<components,layouts,pages>/*.stories.ts`, helpers in `apps/web/src/stories/support` (`Canvas.astro` brings each page's stylesheets) |
| Garmin screens | `apps/garmin/store/screens` and `apps/garmin/resources/drawables` |

Story groups: Start here, Foundations, Brand, UI, Components, Planner, Pages, Garmin, and Site (Astro), the site's own Storybook composed in as a ref. The design system takes the cards from UI, Components, Planner and Brand; Pages, Site and Garmin stay in Storybook and the README links to them.

## Starting new work from a story

1. Write the story first, in the area it belongs to, with real copy (a rider, a loop near Westerlo, km and m) and the tokens. A new page gets an `InertiaPage` story typed with the page's own `Props`, so a later props change breaks `tsc`, not the story.
2. Build the component or page, then point the story at it.
3. Make it a test. Give every prop an `argTypes` entry and every callback a `fn()` spy, and write a `play` (`userEvent`, `expect` and `step` from `storybook/test`) that uses it the way a rider would. A page that talks to the server gets its answers from Mock Service Worker in `beforeEach({ msw }) { msw.use(…) }`, shaped like Laravel's (302 after a POST, 303 after PUT/PATCH/DELETE, errors back on the page), so the flow can be clicked through. Join stories that lead to each other with `linkTo`.
4. It must pass axe in Paper and in Ink. Fix the component, not the story: an unreadable label is a bug riders see too. `a11y: { test: 'todo' }` only with a comment saying why.
5. Every branch that changes a component, a page, a token, or a screen updates its story in the same branch. A token change goes in `resources/design-system/tokens.json` and the CSS together: the build fails when they differ.
6. The content site is Astro, so its stories live in `apps/web/src/stories` and Astro renders them: a story per meaningful prop combination, args and argTypes for every prop, a `play` for what its scripts do, and phone and tablet stories for a page. Set `parameters.sheets` to the page's stylesheets (`page`, `home` or `errors`).
7. The Garmin app cannot run in a browser. A changed watch screen gets new captures in `apps/garmin/store/screens` (that folder's README says how), and its story picks them up.

## Running it

Host `node` is broken on this Mac, so build in DDEV. From a worktree, use its path in the container:

```bash
ddev exec -d /var/www/html/.claude/worktrees/<name>/apps/app php artisan wayfinder:generate --with-form
ddev exec -d /var/www/html/.claude/worktrees/<name>/apps/web npm run build-storybook
ddev exec -d /var/www/html/.claude/worktrees/<name>/apps/app bun run design-system
```

The site's Storybook works on its own too, in `apps/web` (npm, its own lockfile: install with `npm ci --prefix .`):

- `npm run storybook` serves it on port 6007 with live Controls: Astro renders the story again on each change. The planner's `storybook dev` shows it under Site (Astro) from there (or from `STORYBOOK_SITE_URL`).
- `npm run build-storybook` writes `apps/web/storybook-static` (gitignored). Astro renders each story once, so in the build its Controls are fixed. Served beside the planner's at `/storybook/web/`, the planner's build finds it at `../web` for Site (Astro).
- `npm run test-storybook` runs every story in Chromium: it must render, its play function must pass, and axe must find nothing (`a11y.test: 'error'`). In DDEV, where other agents run tests too, add `--no-file-parallelism --browser.api.port=<free port>`.
- `.storybook/astro-scripts.ts` runs the components' `<script>`s in stories. `@storybook-astro/framework` 1.13.0 does this itself; remove the plugin when moving to it.

Tests in `apps/app` (also in Storybook's Testing panel while `storybook dev` runs):

- `bun run test:storybook` runs every story in Chromium twice, Paper then Ink: it must render, its play function must pass, and axe must find nothing. In DDEV add `--no-file-parallelism` (parallel runs crash Chromium there) and `--browser.api.port=<free port>` when another run may be going. Add `--coverage` for the report in `storybook-coverage/`.
- `bun run test:storybook:contrast`, after a build, opens every story and docs page of `storybook-static` in Paper and Ink and fails on any text axe can't read.
- With `bun run storybook` running, the Storybook MCP server (http://localhost:6006/mcp, in `.mcp.json`) lets an agent list components, read their docs, preview stories and run their tests. Use it to find an existing component before writing a new one.

`bun run design-system` builds Storybook into `storybook-static/`, checks `tokens.json` against `app.css` and `planner.css`, and writes `design-system/` (gitignored). Look at it with `python3 -m http.server --directory storybook-static`. CI uploads the same `design-system` folder as an artifact on every run.

## Syncing the design system in Claude

Do this after a merge that changed a story, a component, or `resources/design-system/`, or when asked.

1. Build it as above on an up-to-date `main` (or download the `design-system` artifact from that commit's Storybook run).
2. Read the system with the Artifact tool: `read` its `project/design-system.json` and list its files (`scope: "files"`).
3. Upload each picture in `design-system/publish.json` `uploads` that is new or changed (`asset: true` with `file_paths`), and update its record under `assetGroups` in the index.
4. Publish the files in `publish.json` `files` in one call: `url` the system, `root` the `design-system` folder, `file_path` one file's absolute path, `files` the rest. Send `null` for any `project/components/<Name>/…` that is in the system but no longer built, so a removed story's card goes too.
5. Last, read the index again and publish it with `lastChange` (`by` the person you work for, `via` "Storybook · <short sha>", a one-line `note`), keeping every other key.

The standalone Claude Design app (claude.ai/design) closes on 14 December 2026. Until then, the person can also start `/design-sync` themselves to push the same components there. After that date, the artifact above is the only design system.
