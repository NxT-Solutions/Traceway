---
name: release
description: Cut a Traceway release. Picks the version, moves every merged branch's rider notes into it, and publishes it so production deploys. Use when asked to release, ship, cut, or publish a version of Traceway, or when /release is typed.
---

# Release

A release runs in GitHub Actions (`.github/workflows/release.yml`), so it works the same from a terminal, an agent, or the GitHub phone app (Actions, then Release, then Run workflow). Never tag, push to `main`, or run `gh release create` by hand. The workflow does that, in order, with the `RELEASE_TOKEN` secret.

What a release holds:

- **Riders:** the notes in `apps/app/resources/releases/<version>/`.
  - `_release.md` holds the title and summary.
  - Each merged branch has its own file of changes.
  - The planner's What's new shows them after the deploy.
- **Developers:** the GitHub release, titled with the version, with GitHub's generated notes (pull requests, labels, contributors).

## 1. Check what goes out

```bash
git fetch origin main --tags
gh release view --repo NxT-Solutions/Traceway --json tagName --jq .tagName
git log --oneline "$(gh release view --repo NxT-Solutions/Traceway --json tagName --jq .tagName)"..origin/main
git ls-tree --name-only origin/main apps/app/resources/releases/unreleased/
```

- Read every note in `unreleased/` with `git show origin/main:apps/app/resources/releases/unreleased/<file>`.
- Compare the notes with the commits. Every pull request that changed something riders use should have a note, or the `no-rider-notes` label.
  - If a change riders will notice has no note, say which one and stop. The note goes in through a pull request first, so nothing is lost.
- **Wait for CI on `main`, then go on by yourself.** A release follows the merge it ships, so CI is often still running. Watch it instead of asking:

```bash
run=$(gh run list --repo NxT-Solutions/Traceway --branch main --workflow CI --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$run" --repo NxT-Solutions/Traceway --exit-status
```

  - **Green:** go on to the next step without asking.
  - **Red:** stop. Read the failed jobs (`gh run view "$run" --log-failed`), fix the cause in a pull request through the `pr` skill, and release once that is merged and `main` is green. Never release a red `main`.
- No new commits since the last tag means there is nothing to release. Say so.

## 2. Pick the version

- **Minor:** any note with `section: New`, such as v0.7.0 to v0.8.0.
- **Patch:** only fixes or device notes, such as v0.7.0 to v0.7.1.
- **The person's choice wins.** Use the version they gave. A major version only when they ask for one.
- **Empty:** leaving the version out lets the workflow pick by the same rule.

## 3. Write the title and summary riders see

From the notes:

- **Title:** at most 60 characters, in sentence case, naming the one or two changes riders care about most. For example: `Stops that load, and your privacy in writing`.
- **Summary:** one sentence, at most 140 characters, on what changed for the rider.
- **Style:** plain words for riders over 60. No technical terms, no version numbers, no emoji, and no hype. Never the words beacon, kudos, segment, or suffer.

Show the version, the title, the summary, and the list of notes to the person, then run it. Do not wait for a go: asking for a release is the go. Wait only when the person asked to see it first, or asked for a dry run. A release cut without a title or summary, such as from the phone, gets them from Copilot when the `COPILOT_TOKEN` secret is set. Without it, the release is named by its first change and has no summary.

## 4. Run it

```bash
gh workflow run release.yml --repo NxT-Solutions/Traceway \
  -f version=v0.8.0 -f title="Rivers, and stops that load" -f summary="Routes can follow a river, and water stops load again."
```

- **Preview first** when unsure: add `-f dry_run=true`. The run's summary shows the notes as riders will read them, and nothing is pushed or published.
- **Nothing for riders:** a release with no notes in `unreleased/` still publishes, but riders do not see it in What's new.

## 5. Watch it through

```bash
gh run list --repo NxT-Solutions/Traceway --workflow release.yml --limit 1
gh run watch <run-id> --repo NxT-Solutions/Traceway --exit-status
gh run list --repo NxT-Solutions/Traceway --event release --limit 2
gh run watch <deploy-run-id> --repo NxT-Solutions/Traceway --exit-status
```

Publishing starts Production Deploy and the Garmin build. Report the version, the release link, the title riders see, and how the deploy ended.

## When it fails

- **"Add the RELEASE_TOKEN secret":** the person creates the token. An agent never creates, reads, or pastes a token.
  1. github.com, then Settings, then Developer settings, then Fine-grained tokens.
  2. Choose the resource owner NxT-Solutions, the repository NxT-Solutions/Traceway, and Contents: Read and write.
  3. Run `gh secret set RELEASE_TOKEN --repo NxT-Solutions/Traceway`, which asks for the token.
- **The push to `main` is rejected:** `main` moved during the run. Run it again.
- **A notes file does not read:** the run names the file and why. Fix it in a pull request, then run again.
- **The version is already released:** pick the next one.
