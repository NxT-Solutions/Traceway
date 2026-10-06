# Apple Human Interface Guidelines — Traceway

Vendor-neutral design authority for every coding agent that works this
repo. Cursor, Claude Code, Codex, Grok, and future contributors read
the same files. Do not fork a second copy of these rules inside an app
or a vendor folder.

Traceway is a website: a content site and a route planner. It is not a
Mac app. This folder keeps the parts of Apple’s HIG that make a site
calm and precise, and leaves window chrome, menu bars, and SF Pro
behind.

Official sources (Apple, current):

- [Design principles](https://developer.apple.com/design/human-interface-guidelines/design-principles)
- [Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines)
- [Get started](https://developer.apple.com/design/get-started/)

## Read order

1. `SKILL.md` — when to apply, hard stops, checklists
2. `principles.md` — Purpose through Delight
3. `macos.md` — type, layout, corners, buttons, motion
4. `traceway.md` — tokens, type, and motion on this site
5. `screens.md` — Home, Log in, Planner, Settings

## Agent adapters

| Agent | Loads first | Then reads |
| --- | --- | --- |
| Any | this folder | `hig/SKILL.md` before a visual change |
| Content site | `apps/web/.agents/skills/apple-hig/SKILL.md` | this folder |
| Planner | `apps/app/.agents/skills/apple-hig/SKILL.md` | this folder |

Adapters are pointers. If an adapter and this folder disagree, this
folder wins.
