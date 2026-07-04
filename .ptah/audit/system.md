---
design_status: design-validated
scope: full
---

# System Map — Guestbook

A CodeIgniter guestbook: visitors submit a name, email and message through one
form; entries are validated/sanitized server-side, persisted to a MySQL
`messages` table, and re-rendered newest-first in a timeline. Single controller,
single model, three views.

## Stack — detection evidence

Detection tier: **modern manifest present, but the framework it pins is
end-of-life and unversioned in `require`** — so the stack is classified legacy
and mapped forensically as well.

| Layer | Technology | Evidence |
| :--- | :--- | :--- |
| Language | PHP `>=5.3.7` | `composer.json` `require.php` (line 14) |
| Framework | CodeIgniter `3.1.5` | `system/core/CodeIgniter.php:58` `CI_VERSION = '3.1.5'` |
| Manifest | `composer.json` (type `project`) | root `composer.json`; **no `composer.lock`** on disk |
| Autoloader | Composer autoload **disabled** | `application/config/config.php:139` `composer_autoload = FALSE` |
| Database | MySQL via `mysqli` driver | `application/config/database.php:82` `dbdriver => 'mysqli'` |
| DB access | CI Active Record / Query Builder | `application/config/database.php:74` `query_builder = TRUE`; model uses `$this->db->get/insert` |
| Front controller | `index.php` | root `index.php`; `ENVIRONMENT` defaults to `development` (`index.php:56`) |
| View / assets | Bootstrap-based static theme | `css/`, `js/`, `font/`, `sass/` vendor trees; views under `application/views/` |

`composer.json` also declares dev deps (`phpunit/phpunit 4|5`, `mikey179/vfsStream`)
but no `vendor/` is installed and Composer autoload is off, so those tools are
**not wired** — see `legacy_debt.md#composer-manifest-unresolvable`.

## Architecture

```text
HTTP → index.php (front controller)
     → routes.php (default_controller = guestbook)
     → Guestbook (CI_Controller)
          ├─ index()  → get_messages()  → guestbook_homepage → form + timeline views
          └─ create() → form_validation → set_message() → get_messages() → homepage
     → Guestbook_messages (CI_Model) → CI Active Record → MySQL `messages`
```

- **Routing** — `application/config/routes.php` maps `/` to the `guestbook`
  controller; there is no `404_override`. The form POSTs to `Guestbook/create`.
- **Auto-load** — `application/config/autoload.php` globally loads the `database`
  library and the `url` helper for every request.
- **Persistence** — the `messages` table is declared in `schema/messages.sql`
  (forward-only, idempotent `CREATE TABLE IF NOT EXISTS`), matching the exact
  insert shape `name, email, message` (`received_on` is a DB-side default).

## Boundaries and conventions

- MVC per CI convention: controllers/models/views under `application/`.
- The domain (validation rules, persistence) is **not** isolated from the
  framework — rules are inlined in the controller and the model is bound to CI
  Active Record. No repository, service, or domain layer exists.
- Naming follows CI: `Class` files capitalized, model/library names lower-cased
  at load. Views compose via `$this->load->view(...)` partials.

## Test surface

One test on disk: `application/tests/schema/MessagesSchemaProvisioningTest.php` —
a standalone, PHPUnit-free, **static-only** script gating the `tsk-001` schema
(file exists, forward-only, insert-shape/collation match). It opens no database
and asserts nothing about runtime behavior. There is **zero** coverage of the
controller, model, or views — see `legacy_debt.md#no-test-coverage`.

## Notes for downstream workers

- Do NOT set `design_status: design-validated` here — only the CTO does that on
  approval at the Stage 2 gate.
- Framework `system/` and `user_guide/` are vendor; do not modify or map them
  file-by-file.
