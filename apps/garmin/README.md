# Traceway for Connect IQ

A device app for Garmin bike computers and watches. It lists the rider's saved Traceway routes and loads one as a course over the phone, with no cable. The device follows the course on its own map.

## How it works

1. The app asks `POST /api/device/pairings` for a code and shows it. The rider types it on the site, under Settings, then Devices, and the app collects its token.
2. The route list shows every saved route with its sport, distance, and climb. A multisport plan shows one entry per land leg. A route that is in the device's Courses has its sport tile in green.
3. Choosing a route opens its detail:
   - The route is drawn on the device's own map, framed by the `bounds` from the list. A device without maps (Forerunner 255 and 265) gets the shape drawn on ink.
   - Under it come the name and numbers, and two buttons:
     - The sport's own action: **Ride it**, **Run it**, **Hike it**, or **Walk it**. It loads the course with `HTTP_RESPONSE_CONTENT_TYPE_FIT` and opens it in the device's navigation.
     - **Save**, which only puts the course in Courses.
   - A route already in Courses says so, starts straight away, and swaps Save for **Remove**. Remove asks first, then calls `Course.remove()`. The device only lets the app remove a course it saved itself, so a course that came from Garmin Connect or a cable gets a note to remove it under Courses instead.
   - The app remembers which course it saved for which route (`Application.Storage` key `courses`: download path to course id), so two routes with the same name stay apart, and a course deleted on the device shows as not saved. A course saved before the app kept that link is found by name.
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

The drawables in `resources/drawables` are SVG sources, and the compiler renders them at each device's size. `resources-mip` holds the lime versions for 64-colour screens, and `resources-mip-icons` the lime saved tiles for those watches and the Edge MTB, which draws bitmaps in 64 colours.
- the wordmark, Outfit 600 drawn as outlines, so no font is needed at build time;
- the launcher icon, which is the favicon;
- the sport tiles, the library's lucide glyphs on ink, and in ink on green for a route in Courses;
- the green check.

### Route list

Each route's sport tile sits in the middle of its row. Where a system menu puts an icon, and how big it lets it be, differs per device, so the tile is not a plain bitmap: `TileIcon` (in `Rows.mc`) draws it in the middle of the box the menu hands an icon. On most devices that box spans the row's height. The Edge 1030 family, the 1040, and the Explores give a 24-pixel box in the middle of the row, so they take the smaller tiles in `resources-narrow-icons`. The Edge 530 and 830 give a small box near the top of the row, where nothing can sit in the middle, so those two draw the list's rows themselves (`RouteRow`, the `:customrows` build), in the look of their own menus.

### Button labels

A button label sits in the middle of its button on every device. The devices' fonts differ: on most newer Edges the capitals fill the font's ascent, but on the older Edges and on the watches the font leaves room above them, which drops a label by 2 to 3 pixels. `RouteDetail.labelTop` centres on the ascent, then lifts the label by 8% of the font height (at least 2 pixels) when the ascent is more than three quarters of the height. That rule was measured on every product in the manifest.

Both buttons share one font, the largest whose longest label fits the straight part of the button: its width less its height, so the rounded ends keep clear of the text.

Measure again after changing the buttons, the fonts, or the manifest. Take a capture of a route screen on each device (see Run), then:

```bash
swift tools/label_centre.swift 27 edge1040-route.png
```

The first number is the button height in device pixels, `tall` in `RouteDetail.drawPanel`. Within 0.5 pixels is as centred as whole pixels allow.

## Build and release

CI builds the app, and nothing built is committed. `.github/workflows/garmin.yml` handles it:

- **Every pull request and push to main that changes `apps/garmin` or the workflow, and one run a week:** it installs the pinned Connect IQ SDK and the manifest's devices, then exports `traceway.iq` for every product. The file is uploaded as the `traceway-garmin` artifact and signed with a throwaway key.
- **A published release (`vX.Y.Z`) that changes the app since the last release that attached one:** it stamps that version into `manifest.xml` and signs the package with the real key. It attaches `traceway-garmin-vX.Y.Z.iq` and the store images to the release, then uploads the package to [the store page](https://apps.garmin.com/apps/a0c32e89-323f-4522-9ee8-c6af6cce14c3) as a new version and syncs the listing, with What's New taken from the release's Devices notes. When that fails, the release opens an issue for whoever published it, with the file, the version, and the What's New text for a manual upload under **Upload New Version**.
- **A release that leaves the app alone** (a planner or site change, or only this README or the store images) builds the app as a check, but attaches nothing and opens no issue: there is nothing new to upload. Riders keep the version they have.
- **A push to main, or a manual run on main:** the `Connect IQ Store listing` job makes the store match `store/listing.toml` (`connectiq_store.py sync`). It sends only what differs, so a push that leaves the listing alone changes nothing. When it fails, it opens an issue.
- **Every week and on pull requests:** the same job compares the listing with the store and says what a sync would change, changing nothing (`connectiq_store.py check`). A failure there means the sign-in or the dashboard changed, before a release needs it.

`tools/connectiq_store.py` signs in as the account that owns the listing: `GARMIN_STORE_USERNAME` and `GARMIN_STORE_PASSWORD`, or `GARMIN_USERNAME` and `GARMIN_PASSWORD` when that is the same account. Garmin has no public upload API, so it drives the developer dashboard's own endpoints, and a change on Garmin's side can break it. The weekly check is there to catch that.

### Store listing

The whole listing is in git, and git wins: change it here, not under Edit Details, or the next sync puts it back.

- `store/listing.toml` holds every field of Edit Details: the title, description, category, privacy policy, links, and which image goes where. What's New is the one field it leaves out: each release writes it.
- `store/icon.svg` is the 500 × 500 store icon and the 24-bit device icon: the mark on a solid paper ground, with no Garmin branding.
- `store/device-icon-64.svg` is the 64-colour device icon: the same mark in the colours older devices show.
- `store/hero.svg` is the 1440 × 720 hero image.
- `store/screens/` holds the screen images for the listing, taken in the simulator. Its README says how to take them again.

CI draws each SVG to PNG at the store's size (`connectiq_store.py render --out dist/store`), and the sync draws them the same way. A new or changed screen goes up once its file and its line in `listing.toml` are on main.

The workflow needs these repository secrets:

| Secret | Value |
| --- | --- |
| `GARMIN_USERNAME`, `GARMIN_PASSWORD` | A Garmin account for downloading the SDK device files. It must sign in without a second factor. |
| `GARMIN_STORE_USERNAME`, `GARMIN_STORE_PASSWORD` | The Garmin account that owns the Traceway listing, for the store upload. Leave them out when it is the same account as above. It must sign in without a second factor. |
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
