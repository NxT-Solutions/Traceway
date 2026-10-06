# Traceway homepage — design spec

Hand this file to Claude Design. The output should be a desktop frame, a tablet frame, and a phone frame of one scrolling page, plus a storyboard of the map scene. Do not invent a second product, a second logo, or a second color system.

Traceway is a solo route planner. You drop a pin, name a distance and a sport, and it draws a loop. You export a course file. A Garmin, Wahoo, Hammerhead, Bryton, or COROS follows that file on its own map. There is no feed, no club, and no public routes.

## What is wrong today

The live page is one column of type beside a cartoon map. Green ovals, a beige grid, and a black scribble that crosses itself. Arrows point in conflicting directions. It looks like a child’s drawing of a planner, not a planner. Empty paper fills the rest of the screen.

The next design has to look like a finished product screenshot in motion: a real map, a line that stays on roads, and type that can carry a headline.

## References, and what to take

These are references for craft, not layouts to copy.

- Look 795 Blade RS, Sport Faction, The Cycladist, Mioni Outdoor (Awwwards). One object stays on screen. Scroll changes its state. The page does not jump to a new template every section.
- kontenta AI and FEED Agency. A line draws because scroll progress moves it. The drawing is precise.
- AllTrails (Designerly, 2026). The site feels like the activity. Big type, real places, little chrome.
- Motion (motion.dev) and GSAP. The build will use Motion’s `scroll()` and `animate()` to scrub SVG `pathLength`. Design the states. Do not design a custom animation engine.

Do not reference Strava’s orange, wordmark, or chevron. Do not use the words beacon, kudos, segment, or suffer.

## Brand

| Token | Value | Use |
| --- | --- | --- |
| Paper | `oklch(0.97 0.012 95)` | Page background |
| Ink | `oklch(0.22 0.03 200)` | Text, the chosen route, the pin |
| Chartreuse | `oklch(0.78 0.17 128)` | The one filled button, the via point, small highlights |
| Chartreuse ink | `oklch(0.2 0.04 200)` | Text on chartreuse |
| Muted | `oklch(0.45 0.03 200)` | Secondary text |
| Hairline | `oklch(0.88 0.02 120)` | Borders |
| Card | `oklch(0.985 0.008 95)` | Raised paper |

Font is Outfit, weights 400, 500, and 600 only. No thin, no light, no black. Body size is 17px on desktop, 16px on a phone. Headlines use tight tracking, about −0.04em, and a line height near 0.95. Numbers are tabular.

The mark is one folded path and a dot. It already exists. Do not redraw it as a compass, a pin, or a wordmark.

One filled control on the page: **Open the planner**. Every other action is text or an outline. Corners are 10px on controls and about 24px on the map frame. No pills, no glass, no drop shadow under every card. One soft shadow under the map frame is enough.

## The page

One URL. The header links scroll to sections. They do not open new marketing pages.

Header, sticky, paper, hairline under it:

- Mark and the word Traceway, linking to the top
- How it works → `#generate`
- Sports → `#sports`
- Devices → `#devices`
- Notes → `#notes`
- Log in, text
- Open the planner, filled, linking to `/dashboard`

Then four sections, in this order.

### 1. Generate — `#generate`

This is the product. It occupies the first screen and about four screens of scroll. The frame stays fixed. Only the map and the sentence change.

Left side, top to bottom:

- Kicker: Routes for the bike computer
- Headline: Plan the ride. Send it to the bike.
- One sentence that changes with scroll (copy below)
- Open the planner
- A quiet readout, not a dashboard:
  - Asked — 40 km · paved
  - Loop 1 — 36 km · short
  - Loop 2 — the live distance, ending at 41.6 km
  - Loop 3 — 51 km · long
  - Course — Drawing…, then morning-loop.fit

Right side: one map frame. Not a diagram of blobs.

#### The map

Draw a simplified but credible map of the country south of Westerlo. Pale fields, a blue stream, a few real roads with weight (a yellow through-road, thin local roads). Label Westerlo, Bergom, and Herselt in small muted type, the way a map does. No isometric buildings. No illustrated cyclist. No 3D.

The route is a closed loop that could be ridden:

- It starts and ends on the same pin, on a road in Westerlo.
- It follows roads. It does not cut a field.
- It does not cross itself.
- It does not reverse along the same road. Out and back are different roads.
- Stroke is ink, about 5px on the desktop frame, round caps.
- Two rejected loops are thinner and about 40% opacity. They are also closed, also on roads, also not tangled with the winner. One is shorter to the north. One is longer to the east. They fade out once the winner is chosen. They never sit on top of the winner as a scribble.

Direction marks are the product’s own marks: small white rounded rectangles with a dark arrow, rotated to the bearing of the road at that point. Four of them, spaced on the winner, all agreeing with travel. They appear only after the winner is chosen. They are not chevrons pasted at random angles.

The pin is ink with a chartreuse center. The via point is a chartreuse dot on the southern road. The via bend is a short extra road, still not a self-crossing knot.

#### Scroll storyboard

Scroll progress 0 to 1. Design every state as its own frame so the motion can be checked.

| Progress | Map | Sentence | Readout |
| --- | --- | --- | --- |
| 0.00–0.08 | Map only. Pin fades in. | Drop a pin. Name the distance. Watch the loops appear. | Course: Drawing… Loop 2: 0.0 km |
| 0.08–0.36 | Three loops draw on, following roads. | Several loops leave the pin. Some are short. Some wander. | Loop 1, 2, and 3 become readable. Loop 2 counts up toward 40 km. |
| 0.36–0.58 | The short loop and the long loop fade. The 40 km loop remains. | The close one stays. The others fade. | Loop 1 and Loop 3 dim. Loop 2 stays. |
| 0.58–0.74 | Four direction marks appear along the winner, in travel order. | Arrows point the way you travel, so the line has a direction. | Unchanged. |
| 0.74–0.90 | Via dot appears near Bergom. A short new road draws through it. The loop is still closed and still does not reverse. | A via point bends the loop through the place you wanted. | Loop 2 moves from 40.2 km to 41.6 km. |
| 0.90–1.00 | Full loop, arrows, via. Nothing else draws. | The course file is ready. The computer follows it alone. | Course: morning-loop.fit |

If the system asks for reduced motion, show the last frame immediately and do not make the visitor scroll through four empty screens.

### 2. Sports — `#sports`

Headline: One profile for each sport.

Lede: The same pin. A different idea of a good road. Surface, climb, and quiet streets change with the sport you pick.

Six tiles, ink background, paper type:

| Sport | Line |
| --- | --- |
| Road | Quiet lanes and cycleways. Trunk roads lose. |
| Gravel | Smooth dirt where the map knows it. Pavement still counts. |
| Mountain bike | Trail and rough track. The hills stay in. |
| Run | Paths and pavements. Busy roads drop out. |
| Trail run | Footpaths and dirt, with the climb left on. |
| Walk and hike | The same loop, slower, for a shorter day. |

No icons of shoes or bikes unless they are as simple as the mark. The name is the graphic.

### 3. Devices — `#devices`

One dark ink band.

Kicker, in chartreuse: On the bike

Headline: Leave the phone at home.

Body: Traceway writes a GPX file for almost any head unit, and a FIT course for Garmin. Import it in Connect under Training, then Courses, and sync. Over USB, drop the FIT in Garmin/NewFiles. The computer follows the line on its own map. No signal required. Wahoo, Hammerhead, Bryton, and COROS take the same GPX. A Garmin reads the first 15 characters of the name, so the file keeps a short name beside the full one.

Beside the copy, a file card, not a fake phone:

- morning-loop.fit
- Distance 40.2 km
- Ascent 180 m
- Surface Paved
- Device name Morning loop

### 4. Notes — `#notes`

Headline: Notes from the road.

Lede: Short pieces on planning a line and getting it onto the computer.

Two rows, each a link:

- Onto the computer — The course file is what rides with you. The phone stays home.
- From the driveway — A loop starts at the pin you already know, and comes back there.

Footer, one line: Routes for the bike computer. Map data from OpenStreetMap.

## Layout by device

Design three frames. Same sections, same copy, same map. Do not hide How it works, Sports, Devices, or Notes.

### Phone, 390 wide

- Header is two rows. Row one: mark and Traceway, and the filled Open the planner. Row two: the text links in one horizontal strip that can scroll. Each link is at least 44px tall. No hamburger that hides the product.
- Generate is not a side-by-side crush. Order is headline, one sentence, button, map, then the readout.
- The map frame is full width of the column and at least 240px tall. The loop must still be readable. Do not crop the pin or the via.
- The readout is one horizontal row that scrolls: Asked, Loop 1, Loop 2, Loop 3, Course. The active loop is ink. The others are muted.
- The sticky moment may be shorter than on desktop. The map can stay pinned while the sentence changes, but the headline must not be clipped off the bottom of the screen.
- Sports: one column.
- Devices: copy, then the file card, full width. The card’s rows do not wrap a label away from its value.
- Side inset is 20px. Respect the safe area at the bottom.
- Type: headline about 40px, section titles about 32px.

### Tablet, 834 wide

- Header is one row if it fits at 15px. If it does not, use the phone’s two-row header rather than wrapping links into a ragged block.
- Generate is two columns. Copy and readout on the left, about 320px. Map on the right, filling the rest. Vertically centered as a pair. Nothing is cut off.
- Sports: two columns.
- Devices: copy and file card side by side.
- Side inset is 32px.

### Desktop, 1280 and 1440

- Header is one row. Content width is 1216px, centered.
- Generate fills the viewport under the header. Left column about 420px: headline near 68px, sentence, button, readout stacked. Right column is the map, vertically centered, about 640px wide. The loop has air inside the frame. It is not a postage stamp and it is not cropped.
- Sports: three columns.
- Devices: two columns, copy wider than the file card.
- Section titles about 52px. Space between sections about 80px.

## Motion

Motion scrubs the map to scroll progress. Duration is the scroll, not a timer.

- Pin opacity, then each loop’s `pathLength`, then the losers’ opacity, then each arrow’s opacity in travel order, then the via and the extra road.
- Easing on the scrub is linear. Entrances of the sports tiles and the file card, when they enter the viewport, are 0.7s with `cubic-bezier(0.22, 1, 0.36, 1)`, moving about 28px up and fading in.
- Hover on the filled button darkens chartreuse slightly. It does not grow, bounce, or lift.
- Focus is a 2px ink outline, 2px offset.
- Reduced motion: last map frame, no pinned scroll marathon, no entrance moves.

## What not to design

- A second homepage, a blog grid, a pricing table, or a testimonial wall.
- A phone mockup, a 3D bike, a Lottie cyclist, or a stock landscape photo.
- Glass, grain, gradient meshes, or a dark mode of this page.
- More than one filled button.
- A route that crosses itself, leaves the roads, or shows arrows pointing against travel.

## What the build will do with your frames

The Astro page at `apps/web` will be rebuilt to match the three frames. The map will be one SVG whose paths are the roads you draw, scrubbed with Motion. If a path in the design cannot be ridden without crossing itself, it will be redrawn until it can. The planner app behind Open the planner is out of scope.
