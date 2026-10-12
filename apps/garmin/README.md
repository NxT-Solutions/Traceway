# Traceway for Connect IQ

A device app for Garmin bike computers and watches. It lists the rider's saved Traceway routes and loads one as a course over the phone, with no cable. The device follows the course on its own map.

## How it works

1. The app asks `POST /api/device/pairings` for a code and shows it.
2. The rider types the code on the site under Settings, then Devices.
3. The app polls `POST /api/device/pairings/claim` until it gets its token, then stores it.
4. `GET /api/device/routes` fills a menu. A multisport plan shows one entry per land leg.
5. Choosing an entry downloads `GET /api/device/routes/{id}/fit` with `HTTP_RESPONSE_CONTENT_TYPE_FIT`. The device stores the file as a course. The start key opens it in the device's own navigation.

Removing the device in Settings stops its token at once. The app then asks for a new code.

## Look

The app follows the planner: an ink ground (`#071f20`), paper type (`#f7f5ec`), and the marker green (`#97cb46`) used sparingly. It uses the device's own fonts.

The drawables in `resources/drawables` are SVG sources, and the compiler renders them at each device's size:
- the wordmark, Outfit 600 drawn as outlines, so no font is needed at build time;
- the launcher icon, which is the favicon;
- the sport tiles, the library's lucide glyphs on ink;
- the green check.

## Build and release

CI builds the app, and nothing built is committed. `.github/workflows/garmin.yml` handles it:

- **Every pull request and push to main:** it installs the pinned Connect IQ SDK and the manifest's devices, then exports `traceway.iq` for every product. The file is uploaded as the `traceway-garmin` artifact and signed with a throwaway key.
- **A published release (`vX.Y.Z`):** it stamps that version into `manifest.xml` and signs the package with the real key. It then attaches `traceway-garmin-vX.Y.Z.iq` to the release. Upload that file at the Connect IQ developer dashboard. Garmin has no API for store uploads.

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
