---
section: Fixes
---
- **A busy map server says so.** When the map server does not answer, the planner now asks you to try again in a minute, instead of asking you to move the pin or pick another distance.
- **Route drawing stops less often.** Drawing many loops in a short time could get the map server to stop answering Traceway for an hour, for everyone. Traceway now spreads out what it asks for. If a lot is drawn at once, you may be asked to wait a minute instead.
