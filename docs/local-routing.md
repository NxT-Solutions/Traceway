# Routing on your own machine

The planner asks a Valhalla server for every route, climb, and turn. Out of the box that is the public server at `valhalla1.openstreetmap.de`. It is free and shared, so it is slow at busy times, and it turns a machine away for an hour or more when that machine asks too much. A day of planning and the browser tests can do that.

You can run Valhalla next to the planner in DDEV instead. It builds the roads of one region, such as Belgium or France, once, and then answers in a few milliseconds.

## What your machine needs

Traceway itself, without Valhalla, runs in DDEV with about 2 CPUs, 4 GB of memory for Docker, and 10 GB of disk.

Valhalla adds more, and how much depends on the region. The first start downloads the map from [Geofabrik](https://download.geofabrik.de), then builds tiles from it, with heights for the climb chart. After that it only serves routes.

| Region | Download | Disk after the build | Memory to build | Memory to serve | First build, 2 CPUs |
| --- | --- | --- | --- | --- | --- |
| Luxembourg | 45 MB | 0.4 GB | under 1 GB | under 0.1 GB | 1 minute |
| Belgium (default) | 0.7 GB | 1.9 GB | 2.4 GB | 0.1 GB | 11 minutes |
| Île-de-France | 0.3 GB | about 1 GB | about 1.5 GB | under 0.1 GB | about 5 minutes |
| Netherlands | 1.3 GB | about 4.5 GB | 4–5 GB | about 0.5 GB | about 25 minutes |
| Spain, Italy, Great Britain | 1.4–2.1 GB | about 5–7 GB | 5–7 GB | about 0.5 GB | 30–45 minutes |
| France, Germany | 4.5–4.7 GB | about 15 GB | 10–12 GB | about 1 GB | 1–2 hours |

Luxembourg and Belgium were measured on a Mac with Docker in Colima, with the default limits, download included. With 4 CPUs and no limit, Belgium built in 6 minutes and peaked at 2.8 GB. The other rows are estimates scaled from Belgium by the size of the download, so treat them as a guide. A whole continent is not worth building locally.

Of the 1.9 GB for Belgium, the downloaded map is 0.7 GB, the tiles 0.9 GB (once loose and once packed), the heights 0.3 GB, and the time zones 0.1 GB. When the server only answers routes, it sits near 100 MB of memory and almost no CPU.

The server starts with 2 CPUs and 3 GB of memory, so it cannot crowd out the rest of DDEV. That is enough for Belgium. Raise the limits before you build a bigger region, as shown below.

## Start it

```bash
ddev valhalla start
ddev valhalla logs
```

The first start downloads Belgium and builds it. `ddev valhalla status` shows the region, the limits, the disk used, and whether the server answers. Once it answers, send the planner to it:

```bash
ddev valhalla planner local
```

`ddev valhalla planner public` sends it back to the public server.

## Pick another region

List or search the regions Geofabrik offers. The first column is the id:

```bash
ddev valhalla regions
ddev valhalla regions france
```

Then pick one or more ids. Traceway shows the download sizes, saves your choice, removes the old map, and builds the new one:

```bash
ddev valhalla use france
ddev valhalla use ile-de-france
ddev valhalla use belgium netherlands
```

A smaller region builds faster. If you plan around Paris, `ile-de-france` is a tenth of `france`. Routes stop at the edge of the region you built, so pick one that covers where you ride.

## Change the limits

The region and the limits live in `.ddev/.env.valhalla.local`. Git ignores it, so each person keeps their own. To give France room to build:

```bash
ddev dotenv set .ddev/.env.valhalla.local --valhalla-cpus=4 --valhalla-memory=10g --valhalla-threads=4
ddev valhalla stop
ddev valhalla start
```

| Setting | Default | What it does |
| --- | --- | --- |
| `VALHALLA_REGIONS` | `belgium` | The Geofabrik ids `ddev valhalla use` saved. |
| `VALHALLA_TILE_URLS` | the Belgium download | The maps to build, set by `ddev valhalla use`. |
| `VALHALLA_CPUS` | `2` | The most CPUs the server may use. |
| `VALHALLA_MEMORY` | `3g` | The most memory it may use, with no extra swap. |
| `VALHALLA_THREADS` | `2` | Threads for the build and for answering. Keep it at or below the CPUs. |
| `VALHALLA_HOST_PORT` | `8002` | The port on your machine, at `http://127.0.0.1:8002`. |

Docker's own memory limit sets the ceiling. In Docker Desktop that is Settings, then Resources. With Colima it is `colima start --memory 12`.

## Where the files are

Everything lives in `persistent/valhalla`: the downloaded map, the heights, and the built tiles. Git ignores the folder, and `ddev stop` and `ddev delete` keep it, so the build happens once.

- `ddev valhalla rebuild` builds the tiles again from the map already downloaded.
- `ddev valhalla use <region>` replaces the map and the tiles.
- To free the disk, stop the server and delete `persistent/valhalla`.

## What still uses the network

Map tiles come from OpenFreeMap, `OPENFREEMAP_URL` in `apps/app/.env`. The server reads water, toilets, and cafés out of the same tiles. Place names come from Nominatim, `NOMINATIM_URL`.

## The browser tests

The browser tests draw real routes, so they run best against your own server. They run on your machine, not in DDEV. Once, from `apps/app`, install their packages and Chromium:

```bash
npm ci --prefix tests/Browser
bunx playwright-core install chromium
```

Then send the planner to your server and walk every route feature on a desktop and on a phone:

```bash
ddev valhalla planner local
bun run test:browser:details
```

## When it goes wrong

- The build stops and `docker ps -a` shows the container exited with code 137: it ran out of memory. Raise `VALHALLA_MEMORY`, then `ddev valhalla stop` and `ddev valhalla start`.
- `ddev valhalla status` says the server is not answering: the first build is still running. `ddev valhalla logs` shows the step it is on.
- Routes stop short or say no route matched: the pin is outside the region you built.
- The planner still waits on the public server: run `ddev valhalla planner local`.
