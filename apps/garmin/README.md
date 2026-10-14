# Traceway for Connect IQ

A device app for Garmin bike computers and watches. It lists the rider's saved Traceway routes and loads one as a course over the phone, with no cable. The device follows the course on its own map.

## How it works

1. The app asks `POST /api/device/pairings` for a code and shows it. The rider types it on the site, under Settings, then Devices, and the app collects its token.
2. The route list shows every saved route with its sport, distance, and climb. A multisport plan shows one entry per land leg.
3. Choosing a route opens its detail:
   - The route is drawn on the device's own map, framed by the `bounds` from the list. A device without maps (Forerunner 255 and 265) gets the shape drawn on ink.
   - Under it come the name and numbers, and two buttons:
     - The sport's own action: **Ride it**, **Run it**, **Hike it**, or **Walk it**. It loads the course with `HTTP_RESPONSE_CONTENT_TYPE_FIT` and opens it in the device's navigation.
     - **Save**, which only puts the course in Courses.
   - A route already in Courses says so and starts straight away.
4. Removing the device in Settings stops its token. The app then asks for a new code.

### Buttons and touch

Every screen works with the buttons alone, so the Edge 520 Plus and 530 can use it like a touch Edge:
- Up and down move through the list and between the two buttons.
- Enter or start presses.
- Back goes back. On a panned map it first returns to the preview.

On a touch screen, a tap on a button presses it, and a stray tap does nothing.

### Devices

The manifest covers every Edge from the 520 Plus, 820, Explore, and 1030 onward, plus the Fenix 7 and the Forerunner 255, 265, 955, 965, and 970. The Edge 130, the 130 Plus, and the first Edge 520 cannot run device apps. Two choices are made at build time in `monkey.jungle`:
- The 64-colour MIP watches use `resources-mip`, which swaps the brand green for the palette's lime.
- Devices without maps build the drawn preview.

## Look

The app follows the planner: an ink ground (`#071f20`), paper type (`#f7f5ec`), and the marker green (`#97cb46`) used sparingly. It uses the device's own fonts.

The drawables in `resources/drawables` are SVG sources, and the compiler renders them at each device's size. `resources-mip` holds the lime versions for 64-colour screens.
- the wordmark, Outfit 600 drawn as outlines, so no font is needed at build time;
- the launcher icon, which is the favicon;
- the sport tiles, the library's lucide glyphs on ink;
- the green check.

## Build and release

CI builds the app, and nothing built is committed. `.github/workflows/garmin.yml` handles it:

- **Every pull request and push to main that changes `apps/garmin` or the workflow, and one run a week:** it installs the pinned Connect IQ SDK and the manifest's devices, then exports `traceway.iq` for every product. The file is uploaded as the `traceway-garmin` artifact and signed with a throwaway key.
- **A published release (`vX.Y.Z`):** it stamps that version into `manifest.xml` and signs the package with the real key. It then attaches `traceway-garmin-vX.Y.Z.iq` and the store images to the release. Upload them at the Connect IQ developer dashboard. Garmin has no API for store uploads.

### Store listing

- `store/icon.svg` is the 500 × 500 store icon: the mark on a solid paper ground, with no Garmin branding.
- `store/hero.svg` is the 1440 × 720 hero image.

CI renders both to PNG. Take screenshots in the simulator with File, then Save Screen Capture: the route list, the route detail, and the pairing code, on an Edge and on a round watch.

The workflow needs these repository secrets:

| Secret | Value |
| --- | --- |
| `GARMIN_USERNAME`, `GARMIN_PASSWORD` | A Garmin account for downloading the SDK device files. It must sign in without a second factor. |
| `CONNECTIQ_DEVELOPER_KEY` | `base64 -i ~/.connectiq/developer_key.der`, the key every store update is signed with. |

The workflow pins the SDK version and the hash of the accepted SDK license. When Garmin changes the license, accept it in the SDK manager and copy the new `agreement-hash` from `sdkmanager-config.ini` into the workflow.

### Local builds

You need a free Garmin account to download the [Connect IQ SDK](https://developer.garmin.com/connect-iq/sdk/). Make a signing key once, outside the repo, and back it up:

```bash
mkdir -p ~/.connectiq && openssl genrsa -out ~/.connectiq/developer_key.pem 4096
```

```bash
openssl pkcs8 -topk8 -inform PEM -outform DER -in ~/.connectiq/developer_key.pem -out ~/.connectiq/developer_key.der -nocrypt
```

Build for one device:

```bash
monkeyc -f monkey.jungle -d edge840 -y ~/.connectiq/developer_key.der -o bin/traceway.prg
```

## Run

- **Simulator:** `connectiq`, then `monkeydo bin/traceway.prg edge840`. In the app settings, set the address to the DDEV site, or to a `ddev share` URL if the simulator rejects the local certificate.
- **On a device:** copy `bin/traceway.prg` into `GARMIN/APPS` over USB.

Every product in `manifest.xml` builds with SDK 9.2.0. The manifest version stays `0.0.0`, and a release sets the real one.
