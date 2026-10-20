# Device providers

Traceway gets a saved route onto a device in three ways: a device app that pulls it (Garmin), a connected account Traceway pushes it to (Wahoo, Ride with GPS), or a FIT or GPX file with steps for the brand. This note shows where the next provider plugs in.

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
2. **A connected account Traceway pushes to.** Wahoo and Ride with GPS work this way. They are connections, like Google or GitHub: the rider connects under Settings, then Connections, and the same connection logs them in.
   - Socialite does the sign-in handshake, with Traceway's own providers in `app/Auth/Connections/Providers`. `App\Account\ConnectedIdentity` carries who the rider is there and the keys. The keys are kept only when `ConnectionProvider::keepsKeys()` says so.
   - `connected_accounts` keeps one row per rider and service, with the encrypted keys. `sent_routes` keeps the id each route got at each service, so a second send updates it there.
   - The port is `App\Domain\Courses\Services\CourseService`, which extends `App\Domain\Connections\GrantedAccess`: refresh, publish, revoke. `CourseUpload` carries both files, the full title, the device name, the sport, and an `externalId` (the route id, plus `-{leg}` for a leg).
   - Each service is a Saloon connector and a `CourseService` in `app/Integrations/<Provider>`. After the handshake, everything Traceway does with the keys goes through Saloon. `AppServiceProvider` puts it in the `CourseServices` registry only when its client id and secret are set, so a service without credentials stays hidden.
   - `App\Account\RouteService` has one case per service that takes routes, and `ConnectionProvider::routeService()` points to it. Send to device posts `/routes/saved/{id}/send/{service}`, with `leg` for a multisport plan.
   - A service that refuses the keys marks the connection for reconnecting. Disconnecting, or deleting the account, asks `App\Domain\Connections\GrantedAccesses` to withdraw the keys first.
   - The routing engine still owns the geometry. The files come from the same encoders as the downloads.
3. **A file with steps.** Every brand in Send to device gets the FIT or GPX download it reads and short steps. This needs no account and is the fallback for every other shape.

## Where each brand stands (October 2026)

| Brand | Today | Next |
| --- | --- | --- |
| Garmin | Traceway app (pull), FIT with steps | Courses API push when Garmin takes applications again |
| Wahoo ELEMNT and ACE | Push to the Wahoo app (FIT) | Production access needs Wahoo's review |
| Hammerhead Karoo | Push to Ride with GPS, the rider sends it on with Ride with GPS's Send to Device; or GPX in the Hammerhead Dashboard | Suunto push also reaches a Karoo. A Karoo extension once it can load routes |
| Bryton | Push to Ride with GPS, synced by Bryton Active; or GPX | None without an API |
| COROS | GPX with steps | Partner API `route/push` after the partner application |
| Suunto | GPX with steps | Route import API after the partner program |
| Polar | GPX with steps (Flow web) | AccessLink cannot write routes |
| Amazfit (Zepp) | GPX with steps | None without an API |
| Apple Watch, Wear OS | GPX into a watch app that follows routes | A native app only with demand |

Terra's Routes API could push to Garmin and COROS through Terra's approvals; weigh its price before the direct applications.

## Adding a connection

A connection logs the rider in, and can do more later, such as take routes. A service without credentials stays hidden everywhere, so each one can go live on its own. A new one needs:

1. A `ConnectionProvider` case. Its slug is the Socialite driver and the config key. Fill in the catalog copy: label, group, purpose, capabilities, later, keeps, note, and manage URL, and say whether it keeps keys and the account's email or username. Keep keys only when Traceway needs them, and leave out what the service's terms forbid keeping.
2. A block in `config/services.php`, with its callback at `/auth/<slug>/callback`.
3. The Socialite driver: a built-in one, a SocialiteProviders package, or a provider in `app/Auth/Connections/Providers`. Register the last two in `AppServiceProvider::configureConnections()`. Add the service to `SocialiteIdentityProviders::confirmsEmail()` only when it says it confirmed the email. Until then, a new rider gets the email link first.
4. The secrets in `.github/workflows/production-deploy.yml`, in the env block and as an `upsert_env` line. GitHub keeps every secret name that starts with `GITHUB_`, so name such a secret differently and map it.
5. The keys, the callback, and where to get them in `apps/app/.env.example`, and empty values in `phpunit.xml`. Write down any other step the service needs before it goes live.
6. The service and its company in `apps/web/src/legal.ts`: what Traceway keeps, who handles your data, outside the EU, and the trademarks.
7. Its mark in `resources/js/components/connection-mark.tsx`, in `currentColor` unless the brand asks for its own colours.
8. To take routes too: a `RouteService` case, a `CourseService` in `app/Integrations/<Provider>`, its place in the `CourseServices` registry, and its `service` on the brand in `send-to-device.tsx`.

## How a connection opens an account

- A service that confirms the email opens the account at once: Google, GitHub, and Discord when it says so.
- Any other service, or one that shares no email, gets a one-time link by email from the finish page. It works for 60 minutes. Nothing is opened or linked until the rider clicks it and presses Create account. Until then, Traceway holds who they are at the service, and the keys from Wahoo or Ride with GPS, for at most 60 minutes. Cancelling withdraws those keys.
- The finish form has the same Turnstile check as sign-up, and sends at most 3 links an hour to one address.
- The link connects the service only in the browser that asked for it. The `finish_sign_up` cookie (httpOnly, 60 minutes) holds a random code, and its hash waits with the sign-up. Opened in another browser, the link still opens the account, since the email is proven, but leaves the service out. The rider connects it later under Settings, then Connections. Name any new cookie in the cookie list in `apps/web/src/legal.ts`.
- When the email already has an account, only a confirmed email waits, for 15 minutes, for the rider to log in to that account, and it connects only to that account. Otherwise the rider is told to log in and connect under Settings, then Connections.
- Resetting the password of an account whose email was never confirmed removes every connection on it, withdrawing keys where it can, and its passkeys, two-factor sign-in, and sessions. Whoever opened it with someone else's address keeps no way in.

## Running connections

- `php artisan connections:identify` finds the account number of the Wahoo and Ride with GPS connections carried over from `route_service_accounts`, which cannot log anyone in without it. It asks each service with the keys Traceway already holds. The deploy runs it once the site is open again (`post-deploy.sh`), and the scheduler asks again each night for the ones a service could not answer. One rider's account failing to answer does not hold up the others, but three failures in a row mean the service is down, and the rest of its connections wait for the next run. Each connection that gets its number emails the rider that it now logs them in, as any new way in does. Keys a service turns down are forgotten, and the connection is marked for reconnecting. An account another rider connected already is reported once and marked for reconnecting. Connections marked for reconnecting are not asked again. Running it again is safe.
- It runs in the background on the scheduler, and writes its counts to the log (Sentry Logs in production), since the scheduler throws a command's output away.
