# Traceway

This is a Turborepo.

- `apps/app` is the Laravel planner. Run Composer, Artisan, Pint, PHPStan, and Pest with DDEV, from the repo root: `ddev exec -d /var/www/html/apps/app …`. Read `apps/app/AGENTS.md` before changing PHP or React.
- `apps/web` is the Astro content site. It is the root of the DDEV site (`ddev describe`). The `astro` daemon hot-reloads it.
- `apps/garmin` is the Connect IQ device app, in Monkey C. It pairs with a code and reads `/api/device/*` with a Bearer token. CI builds and signs it (`.github/workflows/garmin.yml`); build output is never committed. See `apps/garmin/README.md`, and `docs/device-providers.md` before adding another provider.
- The planner is the Laravel app on the same host: `/login`, `/register`, `/dashboard`. Its Vite server is the `laravel-vite` daemon, under `/_app/`, so the two dev servers do not collide.

## Product

Traceway plans routes for any sport and exports them as course files for a watch, a GPS, or a bike computer. The browser map is the planner. The device navigates the file on its own basemap. Foot and bike are equal: run, trail run, walk, hike, road, gravel, and mountain bike.

- `app/Domain` does not import Saloon, Eloquent, or HTTP.
- Outbound HTTP uses Saloon connectors in `app/Integrations`. The one exception is the sign-in handshake with a connected service, which is Socialite in `app/Auth/Connections`. Everything done with a connection afterwards is Saloon.
- A connection keeps the service's keys only when Traceway needs them, such as to send routes. See `docs/device-providers.md` before adding one.
- Route geometry comes from the routing engine. An LLM does not emit coordinates.
- A device name is 1 to 15 characters.
- Do not use Strava orange, their wordmark, the chevron mark, or the words beacon, kudos, segment, or suffer.
- Biome formats JavaScript. Pint formats PHP. Blade formatter formats Blade. PHPStan runs at level max with strict rules on. PHPArkitect checks `apps/app/phparkitect.php`. Rector checks `apps/app/rector.php`.

## Storybook and the design system

UI work starts in Storybook (`apps/app/.storybook`, stories in `apps/app/resources/js/stories`). It maps the planner's components and pages, the planner scene, and the Garmin screens, and composes the content site's own Storybook (`apps/web/.storybook`, stories in `apps/web/src/stories`, rendered by Astro). It is public at https://traceway.online/storybook, published with each release: an overview page from the content site, the planner's Storybook at `/storybook/app/` and the site's at `/storybook/web/`. It shares the planner's origin, so a story's requests must be mocked; Storybook blocks any it misses, and Laravel refuses a change sent from a Storybook page (`RefuseStorybookChanges`).

- Every story is a test: it renders, its play function passes, and axe finds nothing, in Paper and in Ink (`bun run test:storybook` in `apps/app`, `npm run test-storybook` in `apps/web`). Pages that call the server mock it with Mock Service Worker, so a flow can be clicked through.
- With `storybook dev` running, the Storybook MCP server (`.mcp.json`) lets an agent list components, read their docs, and preview and test stories.

- A branch that changes a component, a page, a token, or a screen updates its story in the same branch. New UI gets its story first.
- Tokens live in `apps/app/resources/design-system/tokens.json` and in the CSS. `bun run design-system` fails when they differ.
- The Traceway design system in Claude is built from the stories. Never edit it by hand: rebuild and sync it as `.agents/skills/storybook/SKILL.md` says.

## Checks

```bash
ddev exec -d /var/www/html/apps/app composer ci:check
```

While iterating, run the narrow Pest file, then `ddev exec -d /var/www/html/apps/app vendor/bin/pest --parallel --compact`.

To open or update a pull request, or on `/pr`, follow `.agents/skills/pr/SKILL.md`: rebase on `main`, run the whole check in `apps/app/.agents/skills/traceway-pr-check/SKILL.md`, add the rider notes, and prove the change with screenshots in the description. It covers the gate, the builds, the browser suites, every page at every size and rotation, WCAG, GDPR, and a review of the branch.

## Releases

Riders and developers get their own notes:

- **Riders** read the notes in `apps/app/resources/releases`, shown in the planner under What's new.
- **Developers** read the GitHub release, titled with its version, with GitHub's generated notes.

How the rider notes get there:

- **Every branch** that changes something riders use adds one file, `apps/app/resources/releases/unreleased/<branch>.md`, with its section (New, Fixes, or Devices) and the changes in plain words. Follow `.agents/skills/rider-notes/SKILL.md`. A branch with nothing for riders gets the `no-rider-notes` label instead. The Rider Notes check enforces this.
- **Before each pull request update,** rebase on `main` and make sure the notes still cover everything the branch changes.
- **A release** is the Release workflow (`.github/workflows/release.yml`). It moves the notes from `unreleased/` into the new version's folder, commits that to `main`, and publishes the GitHub release, which deploys.
  - When asked to release, or on `/release`, follow `.agents/skills/release/SKILL.md`.
  - Never tag, push to `main`, or create a GitHub release by hand.

## After a merge

When a pull request is merged to `main`, clean up without being asked. Worktrees fill the disk: each one holds its own `vendor` and `node_modules`.

1. From the main checkout, run `git fetch --prune` and `git worktree list`.
2. Remove the worktree whose branch was merged: `git worktree remove .claude/worktrees/<name>`. Then delete the local branch with `git branch -D <branch>`, because a squash merge leaves it looking unmerged.
3. Also remove any other worktree under `.claude/worktrees` whose pull request is merged, or whose commits are all on `main` (`git cherry origin/main <branch>` prints no `+` lines).
4. Finish with `git worktree prune`, then `df -h /` to see the space you got back.

Keep a worktree when any of these are true:
- A running session uses it as its folder.
- It has uncommitted changes (`git status --porcelain`).
- It has commits that are not on `main`.
- It is outside `.claude/worktrees`, like another tool's workspace.

Say which ones you kept, and why.
