---
name: traceway-domain
description: Where a Traceway change belongs. Use when adding domain rules, integrations, HTTP, or a new sport filter.
---

# Traceway domain

The Laravel app lives in `apps/app`. The Astro content site lives in `apps/web`.

- `app/Domain` holds enums, value objects, and ports. It does not import Saloon, Eloquent, or `Illuminate\Http`.
- `app/Integrations` is the only place that talks to another system. HTTP integrations are Saloon connectors. Generate them with `php artisan saloon:connector` and `php artisan saloon:request`.
- `app/Http` stays thin.
- `phparkitect.php` fails `composer ci:check` when a class reaches across a layer. `tests/Arch/BoundariesTest.php` checks the same domain line inside Pest.
- Run `vendor/bin/pest --parallel --compact` from `apps/app` after a domain change.
