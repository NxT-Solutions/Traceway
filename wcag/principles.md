# POUR

Source: [WCAG 2.2](https://www.w3.org/TR/WCAG22/) and the
[WCAG Overview](https://www.w3.org/WAI/standards-guidelines/wcag/).
Thirteen guidelines sit under four principles. Conformance is judged
by the success criteria, not by these titles.

Traceway is a website that draws a loop and exports a course file.
These principles weigh the four surfaces. They do not replace
`criteria.md`. They are not a conformance claim. There is no VoiceOver
claim.

## Perceivable

Information and controls must be presentable in ways people can
perceive.

- The mark, the map, and a spinner each have a text alternative or are
  marked decorative when the words already say the same thing (1.1).
- Headings, the header, labels, and groups are real structure. DOM
  order is the reading order (1.3).
- Ink on paper is the text. Status is a word, not only chartreuse.
  Text stays at or above 4.5:1. The focus ring stays at or above 3:1
  (1.4).

## Operable

Controls and navigation must be operable.

- Every action on Home, Log in, the planner, and Settings has a
  keyboard path. Tab does not get stuck in the map or a layer (2.1).
- Focus follows the visual order: header, then the content, then the
  one filled control. Focus is visible (2.4).
- The accessible name contains the visible label. Draw route, Open the
  planner, and the auth submit say those words in the name. Targets
  are at least 24px (2.5).

## Understandable

The interface must be understandable.

- The page language is set. Labels use the same words as the control.
- An error names the field and sits under it. The label is visible
  before the person submits. A failed log in does not rely on a border
  color (3.3).
- Opening the planner, switching the loop, and exporting a file do
  what the label says. They do not jump somewhere unnamed.

## Robust

The markup must be robust enough that user agents, including assistive
technologies, can interpret it.

- Native controls carry name, role, and value. Busy and invalid are
  exposed, not only painted (4.1.2).
- Draw status is a message the person can hear or read without focus
  moving onto it (4.1.3).
- 4.1.1 Parsing is obsolete in WCAG 2.2.

## Levels

- **A** — minimum. A missing applicable A criterion is a defect.
- **AA** — the bar `criteria.md` aims at for the rows it lists,
  including the A criteria AA would also require.
- This repo does not claim either level. Evidence would live in
  `docs/evidence/` later.
