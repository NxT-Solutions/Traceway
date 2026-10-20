---
name: traceway-routing
description: How Traceway generates a sport route. Use when changing filters, costing, sport defaults, or loop generation.
---

# Traceway routing

Geometry comes from the routing engine through `App\Domain\Routing\RoutingEngine`. Production uses Valhalla (`ROUTER_DRIVER=valhalla`, the public server). An LLM never emits coordinates.

- `ValhallaCosting` turns the rider's filters into Valhalla costing options. On foot it is `pedestrian`; on a bike it is `bicycle`.
- Valhalla's pedestrian costing weighs the kind of way (footway, track, living street, a road with a sidewalk), not a road's class or its surface. On foot, Quiet first makes footways and living streets cheaper. The surface sets tracks (Paved neutral, Any a little cheaper, Dirt cheapest), and Dirt makes footways cheaper too. `highway=path` trails get no pull at all. Nothing on foot keeps a route off a busy road or on paved ground, so the copy must not promise it. Check an option against Valhalla's source for the version in `.ddev/commands/host/valhalla` before relying on it.
- A tapped point snaps with `ValhallaCosting::locate()`, which carries the options that decide which ways a route may use (a bike's `avoid_bad_surfaces`, a hike's `max_hiking_difficulty`). Valhalla keeps a road bike on Paved off anything rougher than compacted, a gravel bike on Paved off grass, the roughest tracks and mountain paths, and every bike off steps. The planner's on-screen snap (to a way drawn on the map) and Build points do not know the sport or surface; only the server snap does.
- `Sport::defaultSurface()` is the one source for the surface a sport starts on. The planner gets it as the `sportSurfaces` prop, and the AI connector uses it when the person does not say.
- `RouteCleanliness` ranks loop candidates: no doubled-back road first, then the closest length.

Tests for the costing and `RouteQuery` are in `tests/Unit/Domain/Routing`. Record Saloon fixtures for HTTP. Do not call a live router from CI. Measure a costing change on the local server (`ddev valhalla start`), not the public one.
