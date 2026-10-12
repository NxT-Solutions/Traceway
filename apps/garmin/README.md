# Traceway for Connect IQ

A device app for Garmin bike computers and watches. It lists the rider's saved Traceway routes and loads one as a course over the phone, with no cable. The device follows the course on its own map.

## How it works

1. The app asks `POST /api/device/pairings` for a code and shows it.
2. The rider types the code on the site under Settings, then Devices.
3. The app polls `POST /api/device/pairings/claim` until it gets its token, then stores it.
4. `GET /api/device/routes` fills a menu. A multisport plan shows one entry per land leg.
5. Choosing an entry downloads `GET /api/device/routes/{id}/fit` with `HTTP_RESPONSE_CONTENT_TYPE_FIT`. The device stores the file as a course. The start key opens it in the device's own navigation.

Removing the device in Settings stops its token at once. The app then asks for a new code.

## Build

You need a free Garmin account to download the [Connect IQ SDK](https://developer.garmin.com/connect-iq/sdk/) and the Monkey C extension for VS Code. No business approval is needed.

Make a signing key once, outside the repo:

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

The same key must sign every store update, so back it up.

## Run

- **Simulator:** `connectiq`, then `monkeydo bin/traceway.prg edge840`. In the app settings, set the address to the DDEV site, or to a `ddev share` URL if the simulator rejects the local certificate.
- **On a device:** copy `bin/traceway.prg` into `GARMIN/APPS` over USB.
- **Store:** export with `monkeyc -e` and upload the `.iq` at the Connect IQ developer dashboard.

Every product in `manifest.xml` builds with SDK 9.2.0.
