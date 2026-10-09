---
name: traceway-cqrs
description: How Traceway separates commands from queries. Use when adding a feature, changing a save, delete, export, or a screen that reads account or route data.
---

# Traceway CQRS

A command changes state. A query reads it. There is no event-sourcing package.

Callers depend on `App\Contracts\Bus\DispatchesCommands`. `App\Bus\CommandBus` is the adapter. The map is registered in `AppServiceProvider`.

## Write path

1. Validate in a form request.
2. Build one readonly command in `App\Commands`. Names, emails, rider ids, route ids, and lines are value objects in `App\Account` and `App\Routes`. The command has no facades.
3. The controller calls `DispatchesCommands::dispatch()`. It does not choose a handler.
4. The handler is the application service. It authorizes through `CurrentRider`, persists through a repository contract, and sends mail through `AccountMailer`.
5. `App\Repositories` is the only place a write touches Eloquent. The password cast and the JSON casts stay on the models.
6. Do not call `Model::create()`, `update()`, or `delete()` from a controller or a handler.

A password rides on the in-memory command only. Do not log it, and do not put it in a table other than `users.password`.

## Read path

Queries live in `App\Queries` and use `DB::table`. A screen returns one readonly page object and calls `toArray()`. The controller does not map rows. Decode JSON columns with `JsonColumn`. Build a signed URL from the route id with `SignedRouteLink`. A JSON body for a drawn line is an `App\Http\Responses` object, not an array written in the controller.

Do not load a `SavedRoute` model to render the library, a share page, a GPX file, or a data export. Do not select `password`, `two_factor_secret`, `two_factor_recovery_codes`, or `remember_token` for a screen.

OpenAPI attributes sit on the controller action and on the request or response that decides the payload. `App\OpenApi\Spec` only names the document and the session cookie. Scalar draws the one document at `/docs`. A call returns the signed-in person's own data, or a course opened with a signed link.

## What stays put

- `App\Domain` stays free of Eloquent, models, Saloon, and HTTP. Calculate route geometry before `SaveRouteCommand`.
- Fortify still owns the login session, two-factor secrets, and passkeys. `DemoLoginController` may load `User` to sign a session in. Changing a password, including the guest reset, is `ChangePasswordCommand`.
- Mail uses the Maizzle templates. `TracewayAccountMailer` sends them. A handler does not build a mailable.
- Seeders, factories, and tests may use Eloquent.

## A new feature

Add the command, the handler, and one line in the bus map. Add a query when a screen reads the result. Point the controller at `dispatch` or the query. Add a feature test for the row or the page, and a test that a second illegal command fails.

An event log can sit behind a handler later. Controllers stay on the bus. Do not add one until a feature needs replay.

The current map is `references/map.md`. The layer rules are `phparkitect.php`. Run `vendor/bin/phparkitect check` after a new command, handler, or query. `rector.php` must stay a clean dry run. Domain code keeps `throw new` and `time()`. It does not call Laravel helpers.
