# Success criteria that apply

Normative titles follow [WCAG 2.2](https://www.w3.org/TR/WCAG22/).
This file is a Traceway mapping, not a substitute for the
Recommendation, and not a conformance claim.

How to Meet: https://www.w3.org/WAI/WCAG22/quickref/

The rows below are what agents must build toward. Levels are the W3C
levels. AA work includes the A criteria listed here. Unlisted criteria
are not declared N/A. A finished row is still not evidence. Evidence
would live in `docs/evidence/` later. There is no VoiceOver claim.

**Intended** means the behavior to build. It does not mean the site
conforms.

4.1.1 Parsing is omitted. It is obsolete in WCAG 2.2.

| SC | Level | Traceway |
| --- | --- | --- |
| 1.1.1 Non-text Content | A | The mark is decorative when the word Traceway sits beside it (`alt=""` or `aria-hidden`). The map has an accessible name. A busy spinner is decorative because the control label remains. No informative image without text. |
| 1.3.1 Info and Relationships | A | One `h1`. Sections use headings. The header is navigation. Settings groups are labeled. Fields use `label`. Visual grouping is in the markup. |
| 1.3.2 Meaningful Sequence | A | DOM order is the reading order and the tab order. CSS does not shuffle that sequence. |
| 1.4.1 Use of Color | A | Chartreuse is not the only signal. The filled control has a text label. Draw failure, loop versus out-and-back, and export state are words. |
| 1.4.3 Contrast (Minimum) | AA | Text is at least 4.5:1. Ink on paper and ink on card are the body pairs. Muted text that carries meaning meets the same ratio. The chartreuse label uses chartreuse ink. |
| 1.4.11 Non-text Contrast | AA | The focus ring is at least 3:1 against the adjacent color. Hairline grouping stays a hairline; do not restyle the product to thicken it. |
| 1.4.13 Content on Hover or Focus | AA | Extra content on hover or focus is dismissible, hoverable, and stays up until dismissed or no longer valid. Hover does not move or cover the control. |
| 2.1.1 Keyboard | A | Home links, auth fields, planner filters, via, the loop switch, Draw route, GPX, FIT, and settings controls all have a keyboard path. No action exists only as a drag or a hover. |
| 2.1.2 No Keyboard Trap | A | Tab can leave the map, the bar, and any open layer. Escape closes a layer that opened over the page. |
| 2.4.3 Focus Order | A | Focus matches visual order: header, then main content, then the primary action. A closed dialog returns focus to the control that opened it. |
| 2.4.7 Focus Visible | AA | `:focus-visible` is visible on every control. Do not remove the ring without an equal replacement. |
| 2.5.3 Label in Name | A | The accessible name contains the visible words. “Draw route”, “Open the planner”, and “Log in” are not replaced by a divergent `aria-label`. |
| 2.5.8 Target Size (Minimum) | AA | Interactive targets are at least 24×24 CSS pixels. Status text is not a target. |
| 3.3.1 Error Identification | A | Errors are text under the field that failed. Color is not the identification. The message says what is wrong. |
| 3.3.2 Labels or Instructions | A | Every field has a visible label. Format hints sit with the field, not only in a placeholder. |
| 4.1.2 Name, Role, Value | A | Use native controls. Expanded, busy, current, and invalid are exposed in the accessibility tree. The loop switch exposes its state. |
| 4.1.3 Status Messages | AA | Draw status is a polite live region (`role="status"`). It updates when drawing starts, when the loop is ready, and when drawing fails. Focus stays where it was. Field errors use `role="alert"` under the field. |
