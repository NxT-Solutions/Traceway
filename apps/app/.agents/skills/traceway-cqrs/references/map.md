# CQRS map

Commands are in `App\Commands`. Handlers in `App\Handlers` are the application services. They depend on contracts in `App\Contracts`. Eloquent writes live in `App\Repositories`. Queries use `DB::table` and do not load Eloquent models. The Pest arch tests in `tests/Arch` check these layers.

## Commands

| Command | Handler | Callers |
| --- | --- | --- |
| `OpenAccountCommand` | Creates the user with the hashed password cast and sends the welcome mail | `CreateNewUser` |
| `UpdateProfileCommand` | Updates name and email. Clears `email_verified_at` when the email changes | `ProfileController::update` |
| `ChangePasswordCommand` | Sets the password and sends the password-changed mail. A guest is allowed after Fortify has checked the reset token | `SecurityController::update`, `ResetUserPassword` |
| `DeleteAccountCommand` | Deletes the user and sends the account-deleted mail. The controller then logs out | `ProfileController::destroy` |
| `ExportPersonalDataCommand` | Builds the export with `PersonalDataQuery` and emails the same JSON | `PrivacyController::export` |
| `SaveRouteCommand` | Inserts one saved route and returns its id and signed share URL | `StoreSavedRouteController` |
| `DeleteRouteCommand` | 404 when the row is gone, 403 when another rider owns it, otherwise deletes it | `DestroySavedRouteController` |

A second delete of the same route is a 404. The row is the state.

## Queries

| Query | Screen or file |
| --- | --- |
| `LibraryQuery` | Saved routes |
| `SharedRouteQuery` | Public share page. One solid line. No owner, no finish split |
| `SharedCourseQuery` | Signed GPX and FIT downloads |
| `PersonalDataQuery::page` | Your data |
| `PersonalDataQuery::document` | `traceway-data.json` and the export mail |

## Left on their own models

- Login session, two-factor secrets, and passkeys stay with Fortify.
- `DemoLoginController` loads a user to start a session.
- Planner preview, build, snap, and the unsaved FIT download do not read these tables.
- Seeders and factories write rows directly.

## Event sourcing

Not installed. `verb_events`, `verb_snapshots`, and `verb_state_events` are dropped. A later log would be written inside a handler. It would not replace the bus, and it would not store a password or a two-factor secret.
