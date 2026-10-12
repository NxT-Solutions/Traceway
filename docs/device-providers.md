# Device providers

Traceway sends a saved route to a device as a course file. Garmin is the only provider today. This note shows where the next one plugs in.

## What is shared

- **Course files.** `App\Domain\Courses\CourseFormat` lists the formats: `fit` and `gpx`. `App\Http\Responses\CourseDownload` turns a saved route, or one land leg, into either one. Share links and device apps get the same bytes.
- **Device names.** `DeviceName` keeps a course name to 15 characters, the most a Garmin shows. Another device with a different limit gets its own rule beside it.
- **Pairing and the device API.** A device app asks `POST /api/device/pairings` for a code, and sends its `provider`. The rider types the code under Settings, then Devices. The app collects a Bearer token and reads:
  - `GET /api/device/routes`, which lists routes with a `title` for menus and a `name` for the course on the device;
  - `GET /api/device/routes/{id}/{format}`, the whole route as a course file;
  - `GET /api/device/routes/{id}/{format}/{leg}`, one land leg of a multisport plan.
- **The provider.** `App\Account\DeviceProvider` has one case per provider. Each device link records its provider, and Settings shows it.

## Two shapes a provider can take

1. **A device app that pulls.** This is how Garmin works: the Connect IQ app in `apps/garmin`. A Karoo or Wear OS app would work the same way. Add a `DeviceProvider` case, and the new app sends it when it pairs. It picks `fit` or `gpx` in the course path. No backend change is needed beyond that case.
2. **A cloud account we push to.** This shape is for providers whose cloud API accepts routes from partners. Each needs its own approval.
   - Put a Saloon connector in `app/Integrations/<Provider>`.
   - Add a port in `app/Contracts` that publishes a course for a rider.
   - Store the rider's OAuth connection, then dispatch a command from Send to device.
   - The course still comes from `CourseDownload`'s encoders. The routing engine still owns the geometry.

Garmin's own Courses API is a push provider of the second kind. It needs an approved business account, which is why Garmin ships as a device app first.
