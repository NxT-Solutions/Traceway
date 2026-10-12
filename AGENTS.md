# Traceway

This is a Turborepo.

- `apps/app` is the Laravel planner. Run Composer, Artisan, Pint, PHPStan, and Pest with DDEV, from the repo root: `ddev exec -d /var/www/html/apps/app …`. Read `apps/app/AGENTS.md` before changing PHP or React.
- `apps/web` is the Astro content site. It is the root of the DDEV site (`ddev describe`). The `astro` daemon hot-reloads it.
- `apps/garmin` is the Connect IQ device app, in Monkey C. It pairs with a code and reads `/api/device/*` with a Bearer token. CI builds and signs it (`.github/workflows/garmin.yml`); build output is never committed. See `apps/garmin/README.md`, and `docs/device-providers.md` before adding another provider.
- The planner is the Laravel app on the same host: `/login`, `/register`, `/dashboard`. Its Vite server is the `laravel-vite` daemon, under `/_app/`, so the two dev servers do not collide.

## Product

Traceway plans routes for any sport and exports them as course files for a watch, a GPS, or a bike computer. The browser map is the planner. The device navigates the file on its own basemap. Foot and bike are equal: run, trail run, walk, hike, road, gravel, and mountain bike.

- `app/Domain` does not import Saloon, Eloquent, or HTTP.
- Outbound HTTP uses Saloon connectors in `app/Integrations`.
- Route geometry comes from the routing engine. An LLM does not emit coordinates.
- A device name is 1 to 15 characters.
- Do not use Strava orange, their wordmark, the chevron mark, or the words beacon, kudos, segment, or suffer.
- Biome formats JavaScript. Pint formats PHP. Blade formatter formats Blade. PHPStan runs at level max with strict rules on. PHPArkitect checks `apps/app/phparkitect.php`. Rector checks `apps/app/rector.php`.

## Checks

```bash
ddev exec -d /var/www/html/apps/app composer ci:check
```

While iterating, run the narrow Pest file, then `ddev exec -d /var/www/html/apps/app vendor/bin/pest --parallel --compact`.
