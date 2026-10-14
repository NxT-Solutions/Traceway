---
name: rider-notes
description: Write the rider notes for a branch, one file in apps/app/resources/releases/unreleased that tells riders what the branch changes for them. Use when opening or updating a pull request that changes the planner, the Garmin app, or the site, or when asked for release notes.
---

# Rider notes for a branch

Riders read these in the planner, under What's new. The technical notes (pull requests, labels, contributors) are written by GitHub on the release, so they are not repeated here.

## The file

One file per branch: `apps/app/resources/releases/unreleased/<branch-name>.md`.

```markdown
---
section: New
---
- **Routes can follow a river.** Pick Rivers under Roads and the loop stays by the water where it can.
- **Faster maps.** The map is sharp at once, even on a slow phone connection.
```

- **Sections:** use `New` for something riders can now do, `Fixes` for something that works again or better, and `Devices` for watches and bike computers. A heading of your own also works.
- **One section per file.** A branch with two kinds of change gets two files, such as `rivers.md` and `rivers-fixes.md`.
- **Don't touch other files.** Never edit another branch's file, and never write into a version folder such as `v0.8.0/`. The Release workflow moves the files there, so branches never clash and nothing is lost.

## How to write it

- **Write for a rider over 60 who just wants routes.** Use short, plain words and say what they will notice on the map, on a ride, or on the watch.
- **Leave out the plumbing.** No file names, APIs, libraries, pull request numbers, or "by @someone".
- **One change per bullet.** Lead with a few words in bold, then one plain sentence.
- **Avoid Strava's words.** Never beacon, kudos, segment, or suffer.

## Keep it whole

- Before every pull request update, rebase on `main` and compare the branch with it: `git diff origin/main...HEAD --stat`. The notes cover everything the branch changes for riders. Update them when the branch grows.
- A branch with nothing for riders, such as CI, tests, docs, or a refactor, gets no file. Label its pull request `no-rider-notes` instead. The Rider Notes check fails a pull request that changes the planner, the Garmin app, or the site with neither.
- Check that the notes read:

```bash
ddev exec -d /var/www/html/apps/app vendor/bin/pest tests/Unit/Quality/RiderNotesFilesTest.php
```
