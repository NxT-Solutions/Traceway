---
name: traceway-quality
description: The check Traceway expects before work is called done. Use when finishing a change or setting up tests.
---

# Traceway quality

From `codebase/`:

- PHP format: `vendor/bin/pint --dirty --format agent`
- Blade: `bun run blade:fix`
- The runtime is PHP 8.5. PHPStan is level max on `phpVersion: 80500`, with every `phpstan-strict-rules` toggle on: `vendor/bin/phpstan analyse`
- Rector is the refactor check: `vendor/bin/rector process --dry-run`, rules in `rector.php`. Pint owns formatting, so Rector does not fight Pint's increment style.
- Architecture is PHPArkitect: `vendor/bin/phparkitect check`, rules in `phparkitect.php`
- Tests: Pest 5, parallel. The full gate is `composer test`. It finishes in a few seconds, so it is the loop to run while iterating. PCOV is installed in the DDEV image and left off (`pcov.enabled=0`) so the site and that gate stay fast.
- Pest TIA (`--tia`) refuses to run here. Pest 5 only enables it when the Composer project is the git root, and this app lives in `apps/app` inside the Traceway repo.
- `composer test:smoke` is the fast HTTP sweep of the public pages, the guest walls, and the rider pages.
- `composer test -- --group=a11y` checks the WCAG color pairs, focus rings, reduced motion, and font hosting. `--group=gdpr` checks export, deletion, and what a shared course may show. Neither group is a conformance claim.
- Browser WCAG is `node wcag.mjs` from `apps/app/tests/Browser`. It runs axe-core. Contrast stays in the Pest group because axe misreads `oklch()`.
- `composer test:mutate` mutation-tests the `mutation` group (value objects, route queries, device names, GPX, stored lines, polyline) and fails under a score of 80. It is slow. Run it before a risky change to that layer, not on every edit. Routing engines stay under their behavioral unit tests.
- Browser smoke is `tests/Browser` and runs on the host: `node planner.mjs` from that directory, with Playwright Chromium. `composer test:perf` is the k6 health check and also runs on the host, because k6 is not in the web image. Agentic browser steps from tester-army/e2e need a model key, so they are not the gate.
- JS: `bun run check:fix` and `bun run types:check`
- One shot: `composer ci:check`

Use the Laravel Boost MCP for docs, schema, and logs. Use the Playwright MCP to click a page a rider would see before saying UI work is done.

New behavior gets a Pest test. Domain rules get a unit test that does not boot the database. HTTP edges get a Saloon mock, not a live call.
