# Device providers

Traceway gets a saved route onto a device in three ways: a device app that pulls it (Garmin), a service account Traceway pushes it to (Wahoo, Ride with GPS), or a FIT or GPX file with steps for the brand. This note shows where the next provider plugs in.

## What is shared

- **Course files.** `App\Domain\Courses\CourseFormat` lists the formats: `fit` and `gpx`. `App\Http\Responses\CourseDownload` turns a saved route, or one land leg, into either one. Share links and device apps get the same bytes. When the route has details (kept on save in `saved_routes.details`, or on each land leg), a FIT course carries a height on every point and course points for turns, water, toilets, and cafés, and a GPX track carries `<ele>` and a `<wpt>` per stop. A device that reads course points warns before each one with no app change.
- **Device names.** `DeviceName` keeps a course name to 15 characters, the most a Garmin shows. Another device with a different limit gets its own rule beside it.
- **Pairing and the device API.** A device app asks `POST /api/device/pairings` for a code, and sends its `provider`. The rider types the code under Settings, then Devices. The app collects a Bearer token and reads:
  - `GET /api/device/routes`, which lists routes with a `title` for menus, a `name` for the course on the device, and the route's `bounds`;
  - `GET /api/device/routes/{id}/line`, the route thinned to at most 150 points, latitude first, for a preview (`/line/{leg}` for one leg);
  - `GET /api/device/routes/{id}/{format}`, the whole route as a course file;
  - `GET /api/device/routes/{id}/{format}/{leg}`, one land leg of a multisport plan.
- **The provider.** `App\Account\DeviceProvider` has one case per provider. Each device link records its provider, and Settings shows it.

## Two shapes a provider can take

1. **A device app that pulls.** This is how Garmin works: the Connect IQ app in `apps/garmin`. A Karoo or Wear OS app would work the same way. Add a `DeviceProvider` case, and the new app sends it when it pairs. It picks `fit` or `gpx` in the course path. No backend change is needed beyond that case.
2. **A service account Traceway pushes to.** Wahoo and Ride with GPS work this way.
   - The port is `App\Domain\Courses\Services\CourseService`: sign-in URL, code for tokens, refresh, publish, revoke. `CourseUpload` carries both files, the full title, the device name, the sport, and an `externalId` (the route id, plus `-{leg}` for a leg).
   - Each service is a Saloon connector and a `CourseService` in `app/Integrations/<Provider>`. `AppServiceProvider` puts it in the `CourseServices` registry only when its client id and secret are set, so a service without credentials stays hidden.
   - `App\Account\RouteService` has one case per service. `route_service_accounts` keeps the encrypted tokens. `sent_routes` keeps the id each route got at each service, so a second send updates it there.
   - The rider connects under Settings, then Devices (`/settings/services/{service}/connect`, with a state in the session). Send to device posts `/routes/saved/{id}/send/{service}`, with `leg` for a multisport plan.
   - A service that refuses the token marks the account for reconnecting. Disconnecting, or deleting the account, revokes the token first.
   - The routing engine still owns the geometry. The files come from the same encoders as the downloads.
3. **A file with steps.** Every brand in Send to device gets the FIT or GPX download it reads and short steps. This needs no account and is the fallback for every other shape.

## Where each brand stands (October 2026)

| Brand | Today | Next |
| --- | --- | --- |
| Garmin | Traceway app (pull), FIT with steps | Courses API push when Garmin takes applications again |
| Wahoo ELEMNT and ACE | Push to the Wahoo app (FIT) | Production access needs Wahoo's review |
| Hammerhead Karoo | Push to Ride with GPS, the rider pins it; or GPX in the Hammerhead Dashboard | Suunto push also reaches a Karoo. A Karoo extension once it can load routes |
| Bryton | Push to Ride with GPS, synced by Bryton Active; or GPX | None without an API |
| COROS | GPX with steps | Partner API `route/push` after the partner application |
| Suunto | GPX with steps | Route import API after the partner program |
| Polar | GPX with steps (Flow web) | AccessLink cannot write routes |
| Amazfit (Zepp) | GPX with steps | None without an API |
| Apple Watch, Wear OS | GPX into a watch app that follows routes | A native app only with demand |

A new push service is a `RouteService` case, an `app/Integrations/<Provider>` adapter, a config block, a secret in `production-deploy.yml`, and its `service` on the brand in `send-to-device.tsx`. Terra's Routes API could push to Garmin and COROS through Terra's approvals; weigh its price before the direct applications.
