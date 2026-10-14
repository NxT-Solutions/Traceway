---
name: pr
description: Open or update a Traceway pull request with proof. Runs the full check, adds the rider notes, takes screenshots of what changed, and writes a description with what changed, why, how it was checked, and what is left. Use when asked to open, create, update, or ship a pull request, or when /pr is typed.
---

# Pull request

A Traceway pull request shows what changed and proves it works: the checks that ran with their counts, and screenshots of every screen it touches. A reviewer who reads only the description knows what to look at.

## 1. Bring the branch up to date

- Work on a branch, never on `main`. Name it `feat/…`, `fix/…`, `chore/…`, `docs/…`, or `ci/…`.
- Rebase on `main`, then compare:

```bash
git fetch origin main
git rebase origin/main
git diff origin/main...HEAD --stat
git log --oneline origin/main..HEAD
```

The description, the rider notes, and the screenshots cover all of that diff, not only the last commit.

## 2. Run the full check

Follow the `traceway-pr-check` skill end to end: the gate, the builds, the browser suites, the screen sheets, WCAG, GDPR, and two fresh reviewers. Fix what they find, and run them again until they are clean. Keep the counts for the description.

## 3. Rider notes

Follow the `rider-notes` skill.

- **Something riders use changed:** `apps/app/resources/releases/unreleased/<branch>.md` says what changed for them.
- **Nothing for riders:** the pull request gets the `no-rider-notes` label.

## 4. Screenshots as proof

Take a screenshot of every screen the branch changes:

- **Sizes:** desktop at 1280 px and phone at 390 px. Add tablet or phone on its side when the layout differs there.
- **Themes:** light and dark.
- **Before and after:** for a change to an existing screen, take the before shot on `main` and the after shot on the branch, at the same size.
- **States:** open the sheet, panel, or dialog the change is about.
- **Sources:** the sheets from `bun run test:browser:screens` (`sheet-<page>.png`) count. Add close-ups through the browser tools.

Name each file for what it shows, such as `whats-new-phone-dark.png`, and keep them in one folder outside the repository. The script below puts them on the `pr-screenshots` branch under `pr/<number>/`. It goes through GitHub's API, so it touches no local checkout, and it prints each file's URL. That branch never merges, and the `main` rules do not cover it.

```bash
apps/app/.agents/skills/pr/upload-screenshots.sh NxT-Solutions/Traceway <number> /path/to/screenshots
```

Show them in the description as `![Phone, dark](https://raw.githubusercontent.com/NxT-Solutions/Traceway/pr-screenshots/pr/<number>/whats-new-phone-dark.png)`. Use a table for before and after. Open the pull request first if it has no number yet, then add the screenshots and edit the description.

## 5. Title, labels, and description

- **Title:** gitmoji and a type, then what it does in plain words, such as `✨ feat: a bell in the header and a What's new page`. The types are ✨ feat, 🐛 fix, ♻️ refactor, ⚡ perf, 🧪 test, 👷 ci, 📝 docs, 🔧 chore, and 🔖 release.
- **Labels:** `enhancement` for something new, `bug` for a fix, `device` for watch and bike computer changes, `documentation`, or `dependencies`. These group the technical notes on the GitHub release. Add `no-rider-notes` when nothing changed for riders.
- **Description:** fill in `.github/PULL_REQUEST_TEMPLATE.md`:
  - **What changes:** for riders first, then for the code: new classes, routes, migrations, and settings.
  - **Why:** the problem, and the choice made where there was one.
  - **How it was checked:** each check with its counts (Pest, the browser suites, axe), the sheets looked at, and what was fixed on the way.
  - **Screenshots:** before and after, desktop and phone, light and dark.
  - **Rider notes:** the file, or why there is none.
  - **WCAG and GDPR:** new UI and its contrast, names, and focus; new personal data, new outside hosts, and processors.
  - **Left for later:** what is not done, plainly.

```bash
gh pr create --repo NxT-Solutions/Traceway --base main --title "<title>" --label <label> --body-file <description.md>
gh pr edit <number> --repo NxT-Solutions/Traceway --body-file <description.md>
```

## 6. After it is open

- Use the session's pull request tools to follow CI, where there are some. Do not poll by hand.
- When a check fails, fix it, run the narrow check locally, push, and say what changed in a comment.
- A force push after a rebase keeps the description true. Update it when the branch grows.
- Never merge without being asked. Never enable auto-merge unless asked.
