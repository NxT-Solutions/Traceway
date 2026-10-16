# Store screens

The screen images on the Connect IQ Store listing, in the order they are uploaded. Each is the device's own screen at its own size: an Edge 1040 (282 × 470) and a Forerunner 965 (454 × 454, outside the circle black). The store takes JPG, GIF or PNG under 150 KB, so the map screens are JPG.

They are taken in the Connect IQ simulator, so CI cannot make them. Take them again when a screen changes:

1. Serve the planner on the host (`php artisan serve --port=8123`) with a rider whose saved routes are real lines, not a few points.
2. Build a scratch copy of `apps/garmin` with `server` in `resources/settings/properties.xml` set to `http://127.0.0.1:8123`, and "Enter it on traceway.online" in `PairView`, so the screens show the address riders use.
3. Run it with `monkeydo <prg> edge1040` and `monkeydo <prg> fr965`, link it under Settings, then Devices, and save each screen with File, then Save Screen Capture.
4. Crop each capture to the device screen and keep it under 150 KB.

Then upload them under Edit Details on [the store page](https://apps.garmin.com/apps/a0c32e89-323f-4522-9ee8-c6af6cce14c3).
