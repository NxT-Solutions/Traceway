# Contributing to Traceway

Thank you for helping. Traceway plans routes for any sport and exports them as course files for a watch, a GPS, or a bike computer. Bug reports, device reports, docs, design, and code all help.

## Before you start

- Read the [Code of Conduct](CODE_OF_CONDUCT.md). It applies in issues, pull requests, and discussions.
- Read the [license](LICENSE). Traceway is open source under the MIT License.
- Sign the [Contributor License Agreement](CLA.md). A bot asks you on your first pull request. A pull request cannot merge until you sign.
- Report a security problem privately. See [SECURITY.md](SECURITY.md).

## Find something to work on

- Issues labelled `good first issue` are small and well described.
- Issues labelled `help wanted` are ready for anyone to pick up.
- For a new feature or a large change, open an issue first so we can agree on the approach before you write the code.
- Comment on an issue before you start, so two people do not do the same work.

## Set up your machine

Install [Docker](https://docs.docker.com/get-docker/) and [DDEV](https://ddev.com/get-started/). Fork the repository, clone your fork, and from the repo root run:

```bash
ddev start
ddev describe
```

The first start installs PHP and Node dependencies, migrates the database, and seeds demo riders. The [README](README.md) lists the URLs, the demo riders, and the everyday commands.

The repo has three apps:

- `apps/app` is the Laravel planner, with React and Inertia. Read [`apps/app/AGENTS.md`](apps/app/AGENTS.md) before you change PHP or React.
- `apps/web` is the Astro content site.
- `apps/mail` holds the account mail sources. The compiled HTML is build output and is not committed. Rebuild it to check a change in Mailpit (see the README).

## Rules of the codebase

- `app/Domain` does not import Saloon, Eloquent, or HTTP.
- Outbound HTTP uses Saloon connectors in `app/Integrations`.
- Route geometry comes from the routing engine. An LLM does not emit coordinates.
- A device name is 1 to 15 characters.
- Foot and bike are equal. A change for one sport should not break another.
- Do not use Strava orange, their wordmark, the chevron mark, or the words beacon, kudos, segment, or suffer.
- Add or update tests for the behaviour you change.
- Do not add a dependency without asking in the issue first.

## Formatting and checks

Biome formats JavaScript. Pint formats PHP. Blade formatter formats Blade. PHPStan runs at level max with strict rules. PHPArkitect and Rector check the architecture.

While you work, run the narrow Pest file, then the whole suite:

```bash
ddev exec -d /var/www/html/apps/app vendor/bin/pest --parallel --compact
```

Before you push, run every check. CI runs the same command on your pull request:

```bash
ddev exec -d /var/www/html/apps/app composer ci:check
```

To fix formatting automatically:

```bash
ddev exec -d /var/www/html/apps/app composer lint
```

```bash
ddev exec -d /var/www/html/apps/app bun run check:fix
```

## Commits

Write one commit per logical change. The subject is a gitmoji, a type, and a short sentence in plain words about what changes for the rider:

```
🐛 fix: welcome a rider after the email is confirmed, once
✨ feat: export a course as a FIT file
📝 docs: explain how to add a routing engine
```

Common types are `feat`, `fix`, `refactor`, `test`, `docs`, and `chore`. In the body, explain why the change is needed and what you changed.

## Pull requests

1. Create a branch from `main` in your fork.
2. Make your change, with tests.
3. Run `composer ci:check`.
4. Open a pull request against `main` and fill in the template.
5. Sign the CLA when the bot asks.

Keep a pull request to one topic. Add screenshots for visual changes, in light and dark mode and at phone width. A maintainer reviews it, may ask for changes, and squashes or merges it when CI passes.

## Questions

Ask in [GitHub Discussions](https://github.com/NxT-Solutions/Traceway/discussions). See [SUPPORT.md](SUPPORT.md) for where each kind of question goes.
