# Traceway

Traceway draws a loop for any sport on a map and exports a course file a watch, a GPS, or a bike computer can follow.

Run, trail run, walk, hike, road, gravel, and mountain bike are all first-class. The hosted planner is at https://traceway.online.

[Contributing](CONTRIBUTING.md) · [Code of Conduct](CODE_OF_CONDUCT.md) · [Security](SECURITY.md) · [Support](SUPPORT.md) · [License](LICENSE)

## Local setup

Install [Docker](https://docs.docker.com/get-docker/) and [DDEV](https://ddev.com/get-started/), then from this directory:

```bash
ddev start
ddev describe
```

Open the URL `ddev describe` prints. On a free machine that is https://traceway.ddev.site.

- `/` is the Astro site in `apps/web`. It hot-reloads.
- `/login`, `/dashboard`, and `/settings` are the Laravel planner in `apps/app`.
- Planner assets hot-reload under `/_app/`.

The first start installs PHP and Node dependencies, migrates the database, and seeds the demo sports. That takes a few minutes.

Local demo riders, password `password`:

- road@traceway.test
- gravel@traceway.test
- run@traceway.test
- hike@traceway.test

The login page has a button for each rider when `APP_ENV=local`.

### Everyday commands

Run these from the repo root. DDEV runs them in the PHP container.

```bash
ddev artisan migrate
ddev exec -d /var/www/html/apps/app composer ci:check
ddev exec -d /var/www/html/apps/app vendor/bin/pest --parallel --compact
ddev logs -s web
```

`ddev logs` shows the Astro server, the planner Vite server, the queue worker, and the scheduler.

### Account mail

The mail sources live in `apps/mail`: one layout in `layouts/main.html`, the button in `components/`, and one file per message in `emails/`. The build inlines the styles so Outlook keeps them and writes the HTML Laravel sends to `apps/app/resources/views/mail/compiled`. Rebuild and commit that output after you change a source:

```bash
cd apps/mail
npm ci
npm run build
```

Local mail goes to Mailpit. `ddev mailpit` opens it.

### If port 80 or 443 is already taken

Add `.ddev/config.local.yaml` (this file stays on your machine):

```yaml
router_http_port: "8080"
router_https_port: "8443"
```

Then `ddev restart`. The site URL includes the port. `ddev start` writes that URL into `apps/app/.env` as `APP_URL`.

### Database

The planner uses SQLite at `apps/app/database/database.sqlite`. Tests use an in-memory SQLite database.

DDEV also starts Postgres 16. To use it, set these in `apps/app/.env` and run `ddev artisan migrate`:

```
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=db
DB_USERNAME=db
DB_PASSWORD=db
```

Queue, cache, and sessions use the same database. Redis is not required.

### Optional GraphHopper

Routing uses public Valhalla (`VALHALLA_URL`) unless you change `ROUTER_DRIVER`. GraphHopper is off until you ask for it:

```bash
ddev start --profiles=routing
```

Put the map files in `persistent/graphhopper`. The container is `graphhopper` on port 8989, which matches `GRAPHHOPPER_URL` in `.env.example`.

## Contributing

Contributions are welcome: bug reports, device reports, docs, design, and code. Read [CONTRIBUTING.md](CONTRIBUTING.md) to get started. Every contributor signs the [Contributor License Agreement](CLA.md) once, on their first pull request.

## License

Traceway is source-available under the [PolyForm Noncommercial License 1.0.0](LICENSE).

You may use, copy, change, and share Traceway for any noncommercial purpose: personal use, hobby projects, study, research, and use by charities, schools, and public bodies. You may run your own copy for yourself, your club, or your friends.

You may not use Traceway, or a changed copy of it, to make money. That includes selling it, charging for a hosted version, or using it inside a paid product or service. For commercial use, ask the maintainer for a separate license.

Because it restricts commercial use, this license is not an OSI-approved open source license.

The name Traceway and its logo are not covered by the license. A fork must use a different name.
