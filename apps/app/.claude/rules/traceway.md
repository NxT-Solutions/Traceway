# Traceway

Domain code does not import Saloon, Eloquent, or HTTP. Outbound HTTP is a Saloon connector, except the sign-in handshake with a connected service, which is Socialite in `app/Auth/Connections`. A connection keeps the service's keys only when Traceway needs them. Route geometry comes from the routing engine. Device names are 1 to 15 characters. Biome formats JavaScript, Pint formats PHP, and PHPStan runs at level max. The marker color is chartreuse. Do not use Strava orange, their wordmark, or their product words.

Open or update a pull request with the `pr` skill: it runs `traceway-pr-check`, adds the rider notes, and puts what it found and screenshots as proof in the description.

A branch that changes something riders use adds its rider notes in `resources/releases/unreleased/<branch>.md` (the `rider-notes` skill), or its pull request gets the `no-rider-notes` label. A release is cut only by the Release workflow, through the `release` skill. Never tag or publish a release by hand.
