# WCAG 2.2 — Traceway

Vendor-neutral accessibility authority for every coding agent that
works this repo. Cursor, Claude Code, Codex, Grok, and future
contributors read the same files. Do not fork a second copy of these
rules inside an app or a vendor folder.

Traceway is a website. These notes aim at WCAG 2.2 for the four
surfaces. They do not say the site conforms.

Official sources (W3C WAI, current):

- [WCAG Overview](https://www.w3.org/WAI/standards-guidelines/wcag/)
- [WCAG 2.2 Recommendation](https://www.w3.org/TR/WCAG22/) (12 December 2024)
- [How to Meet WCAG 2.2](https://www.w3.org/WAI/WCAG22/quickref/)
- [Understanding WCAG 2.2](https://www.w3.org/WAI/WCAG22/Understanding/)
- [Techniques for WCAG 2.2](https://www.w3.org/WAI/WCAG22/Techniques/)

Content that meets 2.2 also meets 2.1 and 2.0. WCAG 3 is a draft and is
not a target.

## Read order

1. `SKILL.md` — when to apply, hard stops, checklists
2. `principles.md` — POUR
3. `criteria.md` — the criteria this repo requires
4. `traceway.md` — tokens, focus, status, errors
5. `screens.md` — Home, Log in, Planner, Settings

## Agent adapters

| Agent | Loads first | Then reads |
| --- | --- | --- |
| Any | this folder | `wcag/SKILL.md` before a visual change |
| Content site | `apps/web/.agents/skills/wcag/SKILL.md` | this folder |
| Planner | `apps/app/.agents/skills/wcag/SKILL.md` | this folder |

Adapters are pointers. If an adapter and this folder disagree, this
folder wins.

## Claim rule

Do not publish “conforms to WCAG 2.2 AA”, “AAA”, or any other
conformance sentence. Token choices, a checklist, and a screenshot are
not evidence. Evidence would live in `docs/evidence/` later. Do not
add that folder in order to claim a result. There is no VoiceOver
claim.
