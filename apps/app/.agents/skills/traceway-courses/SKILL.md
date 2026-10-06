---
name: traceway-courses
description: How a route becomes a file a bike computer can navigate. Use when exporting GPX or FIT, or naming a course.
---

# Traceway courses

The offline artifact is a course file, not a browser tile pack. The head unit uses its own basemap.

- Store a full name and a `DeviceName` of 1 to 15 characters.
- GPX and FIT are the v1 sync. Garmin Connect import, then a device sync, or copy the FIT to `Garmin/NewFiles`.
- Do not scrape Garmin Connect. The Courses API waits on an approved developer app.
