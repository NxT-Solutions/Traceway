---
name: traceway-routing
description: How Traceway generates a sport route. Use when changing filters, scoring, GraphHopper custom models, or loop generation.
---

# Traceway routing

Geometry comes from GraphHopper through `App\Domain\Routing\RoutingEngine`. An LLM never emits coordinates.

Sport behaviour will live in versioned custom-model JSON. The scorer in `CandidateScorer` ranks distance error, surface, climb, cycleway share, trunk roads, and ferries. Unknown surface stays unknown.

Tests for the scorer and `RouteQuery` are in `tests/Unit/Domain/Routing`. Record Saloon fixtures for HTTP. Do not call a live graph from CI.
