---
name: apple-hig
description: Applies Apple Human Interface Guidelines to Traceway, a website. Use for any visual change under apps/web or apps/app resources/js or css, including Home, Log in, Planner, Settings, type, motion, and buttons.
---

# Apple HIG — agent workflow

Read this file before any visual or interaction change. Then read the
linked files that the change touches. Do not invent a second visual
system.

Official authority: `hig/README.md`.

## When this applies

- Any visual change under `apps/web` (pages, layouts, components, CSS)
- Any visual change under `apps/app/resources/js` or
  `apps/app/resources/css`
- A user asks for Apple-like, HIG, polish, spacing, type, motion, or UX
- A screenshot of Home, Log in, Planner, or Settings

## Hard stops (do not violate)

- Purpose first. Traceway draws a loop and exports a course file.
  Chrome recedes. On the planner, the map is the content.
- One filled control per view. Home: “Open the planner”. Planner:
  “Draw route”. An auth view: that form’s submit.
- Both `apps/web/src/styles/global.css` and
  `apps/app/resources/css/app.css` use the same token names and values
  in `traceway.md`. Colors stay Traceway paper, ink, and chartreuse.
  Not Bronze zinc. Not Strava orange `#FC4C02`.
- Do not use the Strava wordmark, the chevron mark, or the words
  beacon, kudos, segment, or suffer.
- Outfit at 400, 500, and 600 only, then `ui-sans-serif`, `system-ui`.
  Self-hosted. No font CDN. No SF Pro requirement. No Thin or Light.
  Body tracking is 0.
- Control radius is 10px. Card radius is 10px plus that card’s padding.
  Pills are for status only.
- Opaque surfaces. Hairline grouping. No fake Liquid Glass. No
  drop-shadow hierarchy.
- Motion is 180ms and never over 200ms. Press is 80ms.
  `prefers-reduced-motion` snaps. Hover does not scale or shadow.
- Busy keeps the label and shows a spinner on that control.
- The mark is one folded path. Copy is short and physical.
- Do not claim WCAG conformance or VoiceOver. See `wcag/README.md`.

## Workflow — any visual change

1. Name the surface: Home, Log in, Planner, or Settings.
2. Read `screens.md` for that surface and `traceway.md` for tokens.
3. Map the change to a principle in `principles.md`.
4. Check `macos.md` for type, corners, the filled button, and motion.
5. Change the existing stylesheet. Do not add a third token file.
6. Do not claim the site conforms to WCAG, and do not claim VoiceOver.

## Workflow — surface checklist

Copy and tick:

```
Surface: ________
- [ ] Purpose: the loop or the course file is the point; chrome recedes
- [ ] One filled control (Open the planner, Draw route, or the auth submit)
- [ ] Header, when shown: mark, Traceway, How it works, Sports, Devices, Notes, Log in or You
- [ ] Type: 11 meta, 13 body, 15 title, 22 page, 26 hero
- [ ] Weights 400 / 500 / 600 only; body tracking 0; Outfit self-hosted
- [ ] Both stylesheets share the token names and values in traceway.md
- [ ] Control radius 10px; card radius = 10px + padding; pills only for status
- [ ] Opaque surfaces, hairline grouping, no fake glass, no shadow hierarchy
- [ ] Hover does not scale or shadow; press 80ms; motion ≤180ms; reduced motion snaps
- [ ] Busy keeps the label and spins on that control
- [ ] :focus-visible is visible
- [ ] Copy is short and physical; no Strava orange, wordmark, chevron, or forbidden words
- [ ] Mark is one folded path
```

## Progressive disclosure

- Principles: [principles.md](principles.md)
- Foundations: [macos.md](macos.md)
- Traceway application: [traceway.md](traceway.md)
- Surfaces: [screens.md](screens.md)
- Adapter map: [README.md](README.md)
