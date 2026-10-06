---
name: traceway-quality
description: The check Traceway expects before work is called done. Use when finishing a change or setting up tests.
---

# Traceway quality

From `codebase/`:

- PHP format: `vendor/bin/pint --dirty --format agent`
- Blade: `bun run blade:fix`
- PHPStan is level max plus `phpstan/phpstan-strict-rules`: `vendor/bin/phpstan analyse`
- Tests: Pest 5, `vendor/bin/pest --parallel --compact`. Narrow with a file path while iterating, then run the parallel suite.
- JS: `bun run check:fix` and `bun run types:check`
- One shot: `composer ci:check`

Use the Laravel Boost MCP for docs, schema, and logs. Use the Playwright MCP to click a page a rider would see before saying UI work is done.

New behavior gets a Pest test. Domain rules get a unit test that does not boot the database. HTTP edges get a Saloon mock, not a live call.
