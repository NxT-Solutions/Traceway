#!/usr/bin/env python3
"""Publishes a new version of Traceway on the Connect IQ Store.

Garmin has no public upload API. This drives the same endpoints the developer
dashboard at apps.garmin.com uses, signed in with the developer's Garmin
account (GARMIN_USERNAME and GARMIN_PASSWORD). It never prints a password,
a cookie, or a ticket: the repository is public, and so are its CI logs.

    connectiq_store.py check
        Signs in and shows the listing: its versions and status, and the names
        of the fields its details have. Changes nothing.

    connectiq_store.py upload --iq traceway.iq --version 0.9.5 --whats-new notes.txt
        Uploads the package as a new version and sets What's New in English.

The dashboard is Garmin's, not a public API, so it can change under us. Every
step checks what came back and stops with a plain message when it is not what
the dashboard itself expects.
"""

import argparse
import http.cookiejar
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

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
LOCALE = "en-US"
USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36"


class StoreError(Exception):
    """A step that did not do what the dashboard expects. The message says which."""


class Store:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

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

    def metadata(self, developer):
        return self.json("GET", f"{API}/developers/{developer}/apps/{APP_ID}/metadata?isShadowApp=false")

    def upload(self, developer, package, version):
        """The dashboard's two calls: the package as a temporary file, then a new version made of it."""
        with open(package, "rb") as handle:
            content = handle.read()
        body, boundary = multipart([("appBinaryFile", os.path.basename(package), "application/octet-stream", content)])
        query = urllib.parse.urlencode({"isNewAppVersion": "true", "appVersion": version, "appId": APP_ID})
        _, answer, _ = self.request(
            "POST",
            f"{API}/iqFiles/validate?{query}",
            body,
            {"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json"},
        )
        result = json.loads(answer)
        temp_file = result.get("tempFileId") if isinstance(result, dict) else None
        if not temp_file:
            raise StoreError(f"The store did not accept the package: {summary(answer)}")

        self.json(
            "POST",
            f"{API}/developers/{developer}/apps/{APP_ID}/version?" + urllib.parse.urlencode({"locale": LOCALE, "juniorAppUpdate": "false"}),
            {"tempFileId": temp_file, "appVersion": version},
            expect=(200, 201, 204),
        )
        return temp_file

    def set_whats_new(self, developer, temp_file, version, whats_new):
        """Saves the details as the dashboard's form does, with What's New set in English."""
        current = self.metadata(developer)
        details = details_from(current, temp_file, version, whats_new)
        body, boundary = multipart([("appMetadata", "blob", "application/json", json.dumps(details).encode())])
        self.request(
            "PUT",
            f"{API}/developers/{developer}/apps/{APP_ID}",
            body,
            {"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json"},
            expect=(200, 201, 204),
        )


def details_from(current, temp_file, version, whats_new):
    """The form's appMetadata, from the listing as it is, with the new version and What's New."""
    if not isinstance(current, dict):
        raise StoreError("The store sent no details for the listing.")
    models = current.get("localizedAppModel") or current.get("localizedAppModels")
    if not isinstance(models, list) or not models:
        raise StoreError(f"The listing's details have no texts per language (fields: {keys(current)}).")

    english = [model for model in models if isinstance(model, dict) and model.get("appLocale") == LOCALE]
    if not english:
        raise StoreError("The listing has no English texts to set What's New on.")
    for model in english:
        model["appWhatsNew"] = whats_new[:4000]

    details = {key: value for key, value in current.items() if key not in ("localizedAppModels",)}
    details["localizedAppModel"] = models
    details["tempFileId"] = temp_file
    details["externalAppVersion"] = version
    return details


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


def check(store):
    developer = store.developer_id()
    current = store.metadata(developer)
    print("Signed in, and the listing answers.")
    print(f"Listing fields: {keys(current)}")
    models = current.get("localizedAppModel") if isinstance(current, dict) else None
    if isinstance(models, list):
        print(f"Languages: {', '.join(str(model.get('appLocale')) for model in models if isinstance(model, dict))}")
        if models and isinstance(models[0], dict):
            print(f"Text fields per language: {keys(models[0])}")
    for key in ("version", "appVersion", "externalAppVersion", "status", "approvalStatus"):
        if isinstance(current, dict) and key in current and not isinstance(current[key], (dict, list)):
            print(f"{key}: {current[key]}")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("check")
    upload = commands.add_parser("upload")
    upload.add_argument("--iq", required=True)
    upload.add_argument("--version", required=True)
    upload.add_argument("--whats-new", required=True, help="a file with the What's New text")
    arguments = parser.parse_args()

    store = Store()
    try:
        store.sign_in(*credentials())
        if arguments.command == "check":
            check(store)
            return 0

        if not re.fullmatch(r"\d+\.\d+\.\d+", arguments.version):
            raise StoreError(f"The version {arguments.version} is not X.Y.Z.")
        with open(arguments.whats_new, encoding="utf-8") as handle:
            whats_new = handle.read().strip()

        developer = store.developer_id()
        temp_file = store.upload(developer, arguments.iq, arguments.version)
        print(f"Uploaded {os.path.basename(arguments.iq)} as version {arguments.version}.")
        store.set_whats_new(developer, temp_file, arguments.version, whats_new)
        print("What's New is set. Garmin reviews the version before riders get it.")
        return 0
    except StoreError as error:
        print(f"::error::{error}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
