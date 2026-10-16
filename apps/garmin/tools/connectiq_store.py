#!/usr/bin/env python3
"""Keeps the Traceway listing on the Connect IQ Store in step with git.

The listing lives in apps/garmin/store/listing.toml: its texts, category,
links, and images. Garmin has no public upload API, so this drives the same
endpoints the developer dashboard at apps.garmin.com uses, signed in with the
developer's Garmin account (GARMIN_USERNAME and GARMIN_PASSWORD). It never
prints a password, a cookie, or a ticket: the repository is public, and so are
its CI logs.

    connectiq_store.py check
        Signs in, compares the listing with git, and says what a sync would
        change. Changes nothing.

    connectiq_store.py sync
        Makes the listing match git: the details, the cover, the device icons,
        the hero image, and the screens. Only what differs is sent.

    connectiq_store.py sync --iq traceway.iq --version 0.9.5 --whats-new notes.txt
        Uploads the package as a new version first, then syncs the listing with
        What's New set in English.

    connectiq_store.py render --out dist/store
        Draws the listing's images as the store gets them, for the release.
        Needs no sign-in.

The dashboard is Garmin's, not a public API, so it can change under us. Every
step checks what came back and stops with a plain message when it is not what
the dashboard itself expects.
"""

import argparse
import base64
import hashlib
import http.cookiejar
import json
import os
import re
import struct
import subprocess
import sys
import tempfile
import tomllib
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zlib

STORE = "https://apps.garmin.com"
API = STORE + "/api/ciq-developerservices"
# The dashboard signs in through Garmin's single sign-on as the apps library,
# and comes back to this address with a ticket that becomes a session cookie.
SERVICE = STORE + "/api/auth/login?returnTo=%2Fen-US%2Fdeveloper%2Fdashboard"
SIGN_IN = "https://sso.garmin.com/sso/signin?" + urllib.parse.urlencode(
    {"service": SERVICE, "clientId": "APPS_LIBRARY", "embedWidget": "true"}
)
# The Traceway listing on the store.
APP_ID = "a0c32e89-323f-4522-9ee8-c6af6cce14c3"
# The dashboard's own locale, for the queries that take one.
LOCALE = "en-US"
USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36"
LISTING = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "store", "listing.toml")

# What the dashboard allows, so a bad file fails here with a plain message.
TITLE_LIMIT = 50
TEXT_LIMIT = 4000
SCREEN_LIMIT = 5
# Each image: its size in pixels when drawn from an SVG, and its largest file size.
IMAGES = {
    "cover": ((500, 500), 300 * 1024),
    "device_icon_64_color": ((128, 128), 150 * 1024),
    "device_icon_24_bit": ((128, 128), 150 * 1024),
    "hero": ((1440, 720), 2048 * 1024),
    "screen": (None, 150 * 1024),
}
# The form field each icon goes up in, next to the details.
ICON_FIELDS = {"cover": "iconFile", "device_icon_24_bit": "iconFile24bit", "device_icon_64_color": "iconFile64bit"}
# Where the listing says each icon is now.
ICON_URLS = {"cover": "iconUrl", "device_icon_24_bit": "appIconUrl24bit", "device_icon_64_color": "appIconUrl64bit"}


class StoreError(Exception):
    """A step that did not do what the dashboard expects. The message says which."""


class Store:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.developer = None

    def request(self, method, url, data=None, headers=None, expect=(200,)):
        request = urllib.request.Request(url, data=data, method=method)
        request.add_header("User-Agent", USER_AGENT)
        for name, value in (headers or {}).items():
            request.add_header(name, value)
        try:
            with self.opener.open(request, timeout=120) as response:
                body = response.read()
                status = response.status
                final = response.geturl()
        except urllib.error.HTTPError as error:
            body = error.read()
            status = error.code
            final = url
        if status not in expect:
            raise StoreError(f"{method} {redact(url)} answered {status}: {summary(body)}")
        return status, body, final

    def json(self, method, url, payload=None, expect=(200,)):
        data = None if payload is None else json.dumps(payload).encode()
        headers = {"Accept": "application/json"}
        if data is not None:
            headers["Content-Type"] = "application/json"
        _, body, _ = self.request(method, url, data, headers, expect)
        return json.loads(body) if body.strip() else None

    def form(self, method, url, parts, expect=(200, 201, 204)):
        body, boundary = multipart(parts)
        return self.request(method, url, body, {"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json"}, expect)

    def sign_in(self, username, password):
        """Signs in like the dashboard does, and keeps the store's session cookie."""
        _, page, _ = self.request("GET", SIGN_IN)
        form = dict(re.findall(rb'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', page))
        fields = {name.decode(): value.decode() for name, value in form.items()}
        fields.update({"username": username, "password": password, "embed": "true"})

        _, result, _ = self.request(
            "POST",
            SIGN_IN,
            urllib.parse.urlencode(fields).encode(),
            {"Content-Type": "application/x-www-form-urlencoded", "Origin": "https://sso.garmin.com"},
        )
        ticket = re.search(rb"ticket=([A-Za-z0-9-]+)", result)
        if ticket is None:
            status = re.search(rb'var\s+status\s*=\s*"([^"]+)"', result)
            reason = status.group(1).decode() if status else "no ticket came back"
            if reason == "FAIL":
                reason = "Garmin turned the username or password down"
            raise StoreError(f"Signing in to Garmin failed: {reason}. Check GARMIN_USERNAME and GARMIN_PASSWORD, and that the account has no two-step sign-in.")

        self.request("GET", SERVICE + "&ticket=" + ticket.group(1).decode(), expect=(200, 302))
        if not any(cookie.domain.endswith("apps.garmin.com") for cookie in self.jar):
            raise StoreError("Garmin accepted the sign-in, but the store gave no session.")
        self.developer = self.developer_id()

    def developer_id(self):
        """The developer the dashboard puts in its paths: the one whose apps include the Traceway listing."""
        user = self.json("GET", API + "/developers")
        if not isinstance(user, dict):
            raise StoreError("The store did not say who is signed in.")

        # The developer record sits inside the account, next to the account's own ids.
        developer = user.get("developer") if isinstance(user.get("developer"), dict) else {}
        candidates = [("developer." + key, value) for key, value in developer.items() if is_id(value)]
        candidates += [(key, value) for key, value in user.items() if is_id(value)]

        for key, value in candidates:
            status, body, _ = self.request("GET", f"{API}/developers/{value}/apps?" + urllib.parse.urlencode({"locale": LOCALE}), expect=(200, 400, 403, 404))
            if status == 200 and APP_ID.encode() in body:
                print(f"The Traceway listing belongs to {key}.")
                return value
        raise StoreError("The Garmin account this signed in with does not own the Traceway listing. Set GARMIN_STORE_USERNAME and GARMIN_STORE_PASSWORD to the account that does: the one that sees Traceway under Uploaded Apps.")

    def app(self):
        return f"{API}/developers/{self.developer}/apps/{APP_ID}"

    def metadata(self):
        current = self.json("GET", self.app() + "/metadata?isShadowApp=false")
        if not isinstance(current, dict) or not isinstance(current.get("localizedAppModel"), list):
            raise StoreError(f"The listing's details are not what the dashboard reads (fields: {keys(current)}).")
        return current

    def reference(self, kind):
        """The store's own lists the form picks from: categories, countries, ANT+ profiles."""
        items = self.json("GET", f"{API}/references/{kind}")
        if not isinstance(items, list):
            raise StoreError(f"The store's list of {kind} is not a list.")
        return items

    def download(self, url):
        if not url:
            return None
        _, body, _ = self.request("GET", urllib.parse.urljoin(STORE, url), expect=(200, 404))
        return body or None

    def upload_version(self, package, version):
        """The dashboard's two calls: the package as a temporary file, then a new version made of it."""
        with open(package, "rb") as handle:
            content = handle.read()
        query = urllib.parse.urlencode({"isNewAppVersion": "true", "appVersion": version, "appId": APP_ID})
        _, answer, _ = self.form("POST", f"{API}/iqFiles/validate?{query}", [("appBinaryFile", os.path.basename(package), "application/octet-stream", content)], expect=(200,))
        result = json.loads(answer)
        temp_file = result.get("tempFileId") if isinstance(result, dict) else None
        if not temp_file:
            raise StoreError(f"The store did not accept the package: {summary(answer)}")

        self.json(
            "POST",
            self.app() + "/version?" + urllib.parse.urlencode({"locale": LOCALE, "juniorAppUpdate": "false"}),
            {"tempFileId": temp_file, "appVersion": version},
            expect=(200, 201, 204),
        )
        return temp_file

    def add_screen(self, image, order):
        self.form("POST", self.app() + "/screenshots?" + urllib.parse.urlencode({"orderValue": order, "juniorAppUpdate": "false"}), [("screenshotFile", image.name, image.content_type, image.content)])

    def delete_screen(self, screen_id):
        self.request("DELETE", f"{self.app()}/screenshots/{screen_id}?juniorAppUpdate=false", expect=(200, 204))

    def save_details(self, details, icons):
        parts = [(ICON_FIELDS[slot], image.name, image.content_type, image.content) for slot, image in icons.items()]
        parts.append(("appMetadata", "blob", "application/json", json.dumps(details).encode()))
        self.form("PUT", self.app(), parts)


class Image:
    """One image as the store gets it: read from git, or drawn from an SVG at the store's size."""

    def __init__(self, slot, path):
        size, limit = IMAGES[slot]
        self.name = os.path.basename(path)
        if path.endswith(".svg"):
            if size is None:
                raise StoreError(f"{path}: a screen is the device's own capture, not an SVG.")
            self.content = draw(path, size)
            if slot == "device_icon_64_color":
                self.content = in_64_colors(self.content)
            elif slot == "device_icon_24_bit":
                self.content = in_24_bits(self.content)
            self.name = os.path.splitext(self.name)[0] + ".png"
        else:
            with open(path, "rb") as handle:
                self.content = handle.read()
        self.content_type = {".png": "image/png", ".jpg": "image/jpeg", ".jpeg": "image/jpeg", ".gif": "image/gif"}.get(os.path.splitext(self.name)[1].lower())
        if self.content_type is None:
            raise StoreError(f"{path}: the store takes PNG, JPG, or GIF.")
        if len(self.content) > limit:
            raise StoreError(f"{path} is {len(self.content) // 1024} KB, and the store takes at most {limit // 1024} KB here.")
        self.digest = hashlib.sha256(self.content).hexdigest()

    def same_as(self, content):
        return content is not None and hashlib.sha256(content).hexdigest() == self.digest


class Plan:
    """What git wants the listing to be, next to what it is, and what a sync would send."""

    def __init__(self, store, listing, whats_new=None):
        self.store = store
        self.current = store.metadata()
        self.folder = os.path.dirname(os.path.abspath(LISTING))
        self.changes = []
        self.details = self.build_details(listing, whats_new)
        self.icons = self.build_icons(listing)
        self.screens = self.build_screens(listing)

    def path(self, name):
        return os.path.join(self.folder, name)

    def change(self, what):
        self.changes.append(what)

    def build_details(self, listing, whats_new):
        current = self.current
        # Fields the form sends that this file does not manage keep their value.
        details = {key: current[key] for key in KEPT_FIELDS if key in current}

        category, subcategory = self.category(listing.get("category", {}))
        details["category"] = category
        if subcategory:
            details["subCategory"] = subcategory
        self.compare("category", current.get("category"), category)
        self.compare("subcategory", current.get("subCategory") or None, subcategory)

        privacy = listing.get("privacy", {})
        policy = privacy.get("policy_url", "").strip() if privacy.get("collects_user_data") else ""
        if privacy.get("collects_user_data") and not policy:
            raise StoreError("listing.toml: an app that collects user data needs privacy.policy_url.")
        set_text(details, "policyUrl", policy)
        self.compare("privacy policy", current.get("policyUrl") or "", policy)

        contact = listing.get("contact", {})
        for field, key, label in (("email", "devEmail", "email"), ("source_url", "sourceUrl", "source code link"), ("video_url", "videoUrl", "video")):
            value = contact.get(field, "").strip()
            set_text(details, key, value)
            self.compare(label, current.get(key) or "", value)
        if not contact.get("email", "").strip():
            raise StoreError("listing.toml: contact.email is required.")
        for field, key, label in (("review_notifications", "reviewNotificationActive", "review email"), ("app_migration", "migrationAllowed", "app migration")):
            value = bool(contact.get(field, False))
            details[key] = value
            self.compare(label, bool(current.get(key)), value)

        paid = bool(listing.get("monetization", {}).get("paid", False))
        if paid:
            raise StoreError("listing.toml: a paid app is set up under Monetization by hand; this sync only keeps a free one.")
        details["paymentModel"] = 0
        self.compare("monetization", current.get("paymentModel") or 0, 0)

        hardware = listing.get("hardware", {})
        profiles = [self.pick("antPlusProfiles", name)["id"] for name in hardware.get("ant_plus_profiles", [])]
        details["selectedAntPlusProfiles"] = {"selectedAntPlusProfiles": profiles}
        live_profiles = (current.get("selectedAntPlusProfiles") or {}).get("selectedAntPlusProfiles") or []
        self.compare("ANT+ profiles", sorted(map(str, live_profiles)), sorted(map(str, profiles)))
        set_text(details, "hardwareProductUrl", hardware.get("product_url", "").strip())
        self.compare("hardware link", current.get("hardwareProductUrl") or "", hardware.get("product_url", "").strip())

        # With no limits the form sends every country; the store then answers with none.
        countries = [self.pick("countries", name)["id"] for name in listing.get("regions", {}).get("countries", [])]
        everywhere = [item["id"] for item in self.store.reference("countries")]
        details["selectedCountries"] = countries or everywhere
        live_countries = sorted(current.get("selectedCountries") or [])
        self.compare("regional limits", [] if live_countries == sorted(everywhere) else live_countries, sorted(countries))

        companion = listing.get("companion", {})
        for field, key, label in (("ios_url", "iosAppUrl", "iOS app"), ("android_url", "androidAppUrl", "Android app")):
            value = companion.get(field, "").strip()
            set_text(details, key, value)
            self.compare(label, current.get(key) or "", value)

        details["localizedAppModel"] = self.texts(listing.get("languages", []), whats_new)
        if "externalAppVersion" in current:
            details["externalAppVersion"] = current["externalAppVersion"]
        return details

    def texts(self, languages, whats_new):
        if not languages:
            raise StoreError("listing.toml: add at least one [[languages]].")
        live = {model.get("appLocale"): model for model in self.current["localizedAppModel"] if isinstance(model, dict)}
        models = []
        for language in languages:
            locale = language.get("locale", "")
            title = language.get("title", "").strip()
            description = language.get("description", "").strip()
            if not title or len(title) > TITLE_LIMIT:
                raise StoreError(f"listing.toml: the {locale} title must be 1 to {TITLE_LIMIT} characters.")
            if not description or len(description) > TEXT_LIMIT:
                raise StoreError(f"listing.toml: the {locale} description must be 1 to {TEXT_LIMIT} characters.")

            current = live.get(locale, {})
            model = {key: value for key, value in current.items() if key not in ("heroImageBase64Encoded",)}
            model.update({"appLocale": locale, "appTitle": title, "appDescription": description})
            self.compare(f"{locale} title", current.get("appTitle", ""), title)
            self.compare(f"{locale} description", (current.get("appDescription") or "").strip(), description)
            if whats_new is not None and is_english(locale):
                model["appWhatsNew"] = whats_new[:TEXT_LIMIT]
                self.compare(f"{locale} What's New", (current.get("appWhatsNew") or "").strip(), whats_new[:TEXT_LIMIT].strip())

            hero = language.get("hero_image", "").strip()
            if hero:
                image = Image("hero", self.path(hero))
                if not image.same_as(self.store.download(current.get("heroImageUrl"))):
                    encoded = base64.b64encode(image.content).decode()
                    model["heroImageBase64Encoded"] = encoded
                    model["heroImageUrl"] = f"data:{image.content_type};base64,{encoded}"
                    model.pop("deleteHeroImage", None)
                    self.change(f"{locale} hero image")
                else:
                    model["deleteHeroImage"] = False
            else:
                model.pop("heroImageUrl", None)
                model["deleteHeroImage"] = True
                if current.get("heroImageUrl"):
                    self.change(f"{locale} hero image removed")
            models.append(model)

        for locale in live:
            if locale not in {language.get("locale") for language in languages}:
                self.change(f"{locale} texts removed")
        return models

    def build_icons(self, listing):
        images = listing.get("images", {})
        coordinates = self.current.get("appFileCoordinates") or {}
        icons = {}
        for slot in ICON_FIELDS:
            name = images.get(slot, "").strip()
            if not name:
                if slot == "cover":
                    raise StoreError("listing.toml: images.cover is required.")
                if coordinates.get(ICON_URLS[slot]):
                    raise StoreError(f"listing.toml: the store has a {slot.replace('_', ' ')}, and this sync cannot remove one. Remove it under Edit Details, or set images.{slot}.")
                continue
            image = Image(slot, self.path(name))
            if not image.same_as(self.store.download(coordinates.get(ICON_URLS[slot]))):
                icons[slot] = image
                self.change(slot.replace("_", " "))
        return icons

    def build_screens(self, listing):
        names = listing.get("images", {}).get("screens", [])
        if not 1 <= len(names) <= SCREEN_LIMIT:
            raise StoreError(f"listing.toml: the store takes 1 to {SCREEN_LIMIT} screens.")
        coordinates = self.current.get("appFileCoordinates") or {}
        urls = coordinates.get("screenshotUrls") or []
        ids = self.current.get("screenshotIds") or []
        live = [(screen_id, self.store.download(url)) for screen_id, url in zip(ids, urls)]

        steps = []
        for order, name in enumerate(names):
            image = Image("screen", self.path(name))
            existing = live[order] if order < len(live) else None
            if existing and image.same_as(existing[1]):
                continue
            steps.append((order, image, existing[0] if existing else None))
            self.change(f"screen {order + 1}")
        extra = [screen_id for screen_id, _ in live[len(names):]]
        for order in range(len(names), len(live)):
            self.change(f"screen {order + 1} removed")
        return steps, extra

    def category(self, wanted):
        name = wanted.get("name", "").strip()
        if not name:
            raise StoreError("listing.toml: category.name is required.")
        category = self.pick("categories", name)
        subcategory = None
        if wanted.get("subcategory", "").strip():
            subcategory = find(category.get("children") or [], wanted["subcategory"].strip())
            if subcategory is None:
                raise StoreError(f"listing.toml: {category.get('name')} has no subcategory {wanted['subcategory']!r}.")
        return category["id"], subcategory["id"] if subcategory else None

    def pick(self, kind, wanted):
        found = find(self.store.reference(kind), str(wanted))
        if found is None:
            raise StoreError(f"listing.toml: the store has no {kind} entry {wanted!r}.")
        return found

    def compare(self, label, current, wanted):
        if current != wanted:
            self.change(label)

    def apply(self, temp_file=None, version=None):
        """Screens first, then the details with the icons, as the dashboard's Submit does."""
        steps, extra = self.screens
        count = len((self.current.get("appFileCoordinates") or {}).get("screenshotUrls") or [])
        for order, image, old in steps:
            # The store keeps at least one screen, so the last one is replaced after its successor is up.
            if old and count == 1:
                self.store.add_screen(image, order)
                self.store.delete_screen(old)
            elif old:
                self.store.delete_screen(old)
                self.store.add_screen(image, order)
            else:
                self.store.add_screen(image, order)
                count += 1
            print(f"Screen {order + 1}: {image.name}")
        for screen_id in extra:
            self.store.delete_screen(screen_id)

        details = dict(self.details)
        if temp_file:
            details["tempFileId"] = temp_file
            details["externalAppVersion"] = version
        self.store.save_details(details, self.icons)


# Fields the form sends back as they are: this file does not manage them.
KEPT_FIELDS = ("additionalAndroidAppUrls", "additionalIosAppUrls", "betaApp", "shadowApp", "selectedActivityTypes", "requiringExternalSubscription", "requestingTips")


def set_text(details, key, value):
    if value:
        details[key] = value
    else:
        details.pop(key, None)


def find(items, wanted):
    """An entry from one of the store's lists, by its id or its name, in any case."""
    wanted = wanted.casefold()
    for item in items:
        if not isinstance(item, dict):
            continue
        names = [item.get(key) for key in ("id", "name", "key", "code", "countryCode", "isoCode", "displayName")]
        if any(isinstance(name, (str, int)) and str(name).casefold() == wanted for name in names):
            return item
    return None


def draw(path, size):
    """An SVG drawn at the store's size, with librsvg as the release images are."""
    width, height = size
    try:
        return subprocess.run(["rsvg-convert", "--width", str(width), "--height", str(height), path], check=True, capture_output=True).stdout
    except FileNotFoundError:
        raise StoreError("rsvg-convert is missing: install librsvg2-bin.") from None
    except subprocess.CalledProcessError as error:
        raise StoreError(f"rsvg-convert could not draw {path}: {error.stderr.decode(errors='replace')[:200]}") from None


# The store tells the two device icons apart by their pixel format, and turns
# a second icon of the same size and format down as a duplicate. So the
# 64-colour icon is an 8-bit palette PNG, and the 24-bit icon is plain RGB.
def in_64_colors(png):
    """The icon as a palette PNG, each colour moved to the nearest of the 64 that older devices show: 0, 85, 170, or 255 per channel."""
    width, height, channels, pixels = read_png(png)
    level = [min(range(0, 256, 85), key=lambda target: abs(target - value)) for value in range(256)]
    palette = {}
    rows = []
    for row in range(height):
        line = bytearray([0])
        for column in range(width):
            start = (row * width + column) * channels
            red, green, blue = (level[value] for value in pixels[start:start + 3])
            # A see-through pixel stays see-through; anything else is solid.
            alpha = 0 if channels == 4 and pixels[start + 3] < 128 else 255
            color = (0, 0, 0, 0) if alpha == 0 else (red, green, blue, 255)
            line.append(palette.setdefault(color, len(palette)))
        rows.append(bytes(line))
    colors = sorted(palette, key=palette.get)
    plte = b"".join(bytes(color[:3]) for color in colors)
    trns = bytes(color[3] for color in colors) if any(color[3] == 0 for color in colors) else None
    return write_png(width, height, 3, b"".join(rows), plte, trns)


def in_24_bits(png):
    """The icon as an RGB PNG, with any see-through pixel laid on white."""
    width, height, channels, pixels = read_png(png)
    rows = []
    for row in range(height):
        line = bytearray([0])
        for column in range(width):
            start = (row * width + column) * channels
            if channels == 4:
                alpha = pixels[start + 3]
                line += bytes((value * alpha + 255 * (255 - alpha)) // 255 for value in pixels[start:start + 3])
            else:
                line += pixels[start:start + 3]
        rows.append(bytes(line))
    return write_png(width, height, 2, b"".join(rows))


def read_png(png):
    if png[:8] != b"\x89PNG\r\n\x1a\n":
        raise StoreError("The drawn icon is not a PNG.")
    position, data, header = 8, b"", None
    while position < len(png):
        length, kind = struct.unpack(">I4s", png[position:position + 8])
        chunk = png[position + 8:position + 8 + length]
        position += 12 + length
        if kind == b"IHDR":
            header = struct.unpack(">IIBBBBB", chunk)
        elif kind == b"IDAT":
            data += chunk
    width, height, depth, color, _, _, interlace = header
    if depth != 8 or color not in (2, 6) or interlace:
        raise StoreError("The drawn icon is not an 8-bit RGB or RGBA PNG.")
    channels = 4 if color == 6 else 3
    raw = zlib.decompress(data)
    stride = width * channels
    pixels = bytearray()
    previous = bytearray(stride)
    for row in range(height):
        start = row * (stride + 1)
        kind, line = raw[start], bytearray(raw[start + 1:start + 1 + stride])
        for index in range(stride):
            left = line[index - channels] if index >= channels else 0
            up = previous[index]
            corner = previous[index - channels] if index >= channels else 0
            if kind == 1:
                line[index] = (line[index] + left) & 255
            elif kind == 2:
                line[index] = (line[index] + up) & 255
            elif kind == 3:
                line[index] = (line[index] + (left + up) // 2) & 255
            elif kind == 4:
                estimate = left + up - corner
                near = min((abs(estimate - left), 0, left), (abs(estimate - up), 1, up), (abs(estimate - corner), 2, corner))[2]
                line[index] = (line[index] + near) & 255
        pixels += line
        previous = line
    return width, height, channels, bytes(pixels)


def write_png(width, height, color, rows, palette=None, transparency=None):
    """A PNG of filtered rows: color 2 is RGB, 3 is a palette, 6 is RGBA."""
    def chunk(kind, content):
        return struct.pack(">I", len(content)) + kind + content + struct.pack(">I", zlib.crc32(kind + content) & 0xFFFFFFFF)

    png = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, color, 0, 0, 0))
    if palette is not None:
        png += chunk(b"PLTE", palette)
    if transparency is not None:
        png += chunk(b"tRNS", transparency)
    return png + chunk(b"IDAT", zlib.compress(rows, 9)) + chunk(b"IEND", b"")


def is_english(locale):
    return isinstance(locale, str) and (locale == "en" or locale.startswith("en-") or locale.startswith("en_"))


def multipart(parts):
    boundary = "traceway" + uuid.uuid4().hex
    chunks = []
    for name, filename, content_type, content in parts:
        chunks.append(f"--{boundary}\r\n".encode())
        chunks.append(f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'.encode())
        chunks.append(f"Content-Type: {content_type}\r\n\r\n".encode())
        chunks.append(content)
        chunks.append(b"\r\n")
    chunks.append(f"--{boundary}--\r\n".encode())
    return b"".join(chunks), boundary


def is_id(value):
    return isinstance(value, str) and re.fullmatch(r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}", value) is not None


def keys(value):
    return ", ".join(sorted(value.keys())) if isinstance(value, dict) else type(value).__name__


def summary(body):
    """A short, safe line from an answer: never more than the start, never a token."""
    text = body.decode(errors="replace") if isinstance(body, bytes) else str(body)
    text = re.sub(r"(ticket|token|session|cookie)[=:]\S+", r"\1=…", text, flags=re.I)
    return re.sub(r"\s+", " ", text)[:300]


def redact(url):
    return re.sub(r"ticket=[^&]+", "ticket=…", url)


def credentials():
    username = os.environ.get("GARMIN_USERNAME", "").strip()
    password = os.environ.get("GARMIN_PASSWORD", "").strip()
    if not username or not password:
        raise StoreError("Set GARMIN_USERNAME and GARMIN_PASSWORD.")
    return username, password


def read_listing():
    try:
        with open(LISTING, "rb") as handle:
            return tomllib.load(handle)
    except (OSError, tomllib.TOMLDecodeError) as error:
        raise StoreError(f"listing.toml could not be read: {error}") from None


def render(listing, folder):
    """The listing's drawn images, named as the release attaches them."""
    base = os.path.dirname(os.path.abspath(LISTING))
    images = listing.get("images", {})
    wanted = [(slot, images.get(slot, "").strip(), f"traceway-{slot.replace('_', '-')}.png") for slot in ICON_FIELDS]
    wanted = [("cover" if slot == "cover" else slot, name, "traceway-store-icon.png" if slot == "cover" else file) for slot, name, file in wanted]
    for language in listing.get("languages", []):
        suffix = "" if is_english(language.get("locale")) else "-" + language.get("locale", "")
        wanted.append(("hero", language.get("hero_image", "").strip(), f"traceway-store-hero{suffix}.png"))
    os.makedirs(folder, exist_ok=True)
    for slot, name, file in wanted:
        if not name.endswith(".svg"):
            continue
        image = Image(slot, os.path.join(base, name))
        with open(os.path.join(folder, file), "wb") as handle:
            handle.write(image.content)
        print(f"{file}: {len(image.content) // 1024} KB")


def report(plan):
    if plan.changes:
        print("The store differs from git in: " + ", ".join(plan.changes) + ".")
    else:
        print("The store matches git.")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("check")
    drawing = commands.add_parser("render")
    drawing.add_argument("--out", required=True, help="the folder to draw the images into")
    sync = commands.add_parser("sync")
    sync.add_argument("--iq", help="a package to upload as a new version first")
    sync.add_argument("--version", help="the new version, X.Y.Z")
    sync.add_argument("--whats-new", help="a file with the What's New text")
    sync.add_argument("--result", help="a file to write how far it got: none, uploaded, or done")
    arguments = parser.parse_args()

    store = Store()
    progress = "none"
    try:
        listing = read_listing()
        if arguments.command == "render":
            render(listing, arguments.out)
            return 0
        if arguments.command == "sync" and arguments.iq:
            if not arguments.version or not re.fullmatch(r"\d+\.\d+\.\d+", arguments.version):
                raise StoreError(f"The version {arguments.version} is not X.Y.Z.")
            if not arguments.whats_new:
                raise StoreError("A new version needs --whats-new.")
        whats_new = None
        if arguments.command == "sync" and arguments.whats_new:
            with open(arguments.whats_new, encoding="utf-8") as handle:
                whats_new = handle.read().strip()

        store.sign_in(*credentials())
        if arguments.command == "check":
            plan = Plan(store, listing)
            print("Signed in, and the listing answers.")
            report(plan)
            return 0

        temp_file = None
        if arguments.iq:
            temp_file = store.upload_version(arguments.iq, arguments.version)
            progress = "uploaded"
            print(f"Uploaded {os.path.basename(arguments.iq)} as version {arguments.version}.")

        plan = Plan(store, listing, whats_new)
        report(plan)
        if plan.changes or temp_file:
            plan.apply(temp_file, arguments.version)
            print("The listing matches git now. Garmin reviews the changes before riders see them.")
        progress = "done"
        return 0
    except StoreError as error:
        print(f"::error::{error}")
        return 1
    finally:
        if getattr(arguments, "result", None):
            with open(arguments.result, "w", encoding="utf-8") as handle:
                handle.write(progress + "\n")


if __name__ == "__main__":
    sys.exit(main())
