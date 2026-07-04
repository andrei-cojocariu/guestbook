# System Map — Guestbook

<!-- design_status: design-validated -->
<!-- Stage 2 (Design) proposal awaiting human CTO sign-off. Do NOT set
     design-validated here — only the CTO does that on approval. -->

A single-page CodeIgniter 3 guestbook: visitors submit a name, email and message
through a form; entries are persisted to MySQL and rendered back as a timeline.

## Stack

| Layer | Technology | Version | Evidence tier |
| :--- | :--- | :--- | :--- |
| Language | PHP | `>=5.3.7` (declared) | Modern manifest |
| Framework | CodeIgniter | `3.1.5` | Modern manifest + source constant |
| Database | MySQL via `mysqli` | server 5.x assumed | Config |
| Front-end | Bootstrap 3, jQuery, jQuery Validate | vendored, unpinned | Heuristic |
| Build/CSS | Sass (`sass/style.scss`) | unpinned | Heuristic |
| Tests | two standalone gate scripts, no framework wired | `application/tests/schema/`, `application/tests/infra/` | Source |
| Dev/CI runtime | Docker (`php:5.6.40-apache` + `mysql:5.7.44`), pinned, no floating tags | `Dockerfile`, `docker-compose.yml` (`tsk-002`) | Source |

### Detection evidence

- `composer.json` → `"name": "codeigniter/framework"`, `"require": {"php":
  ">=5.3.7"}`. No application-level dependencies; framework is vendored in-tree.
- `system/core/CodeIgniter.php:58` → `const CI_VERSION = '3.1.5';` (released
  2017, series end-of-life). This pins the framework precisely.
- `application/config/database.php:82` → `'dbdriver' => 'mysqli'`.
- `composer.lock` is committed (`tsk-002`, `packages: []`, generated with
  `--no-dev`); front-end libraries are still committed under `js/` and `css/`
  with no lockfile, so **those** versions remain unversioned (Legacy Protocol).
- No `phpunit.xml`, no PHPUnit wired. `application/tests/schema/` holds one
  standalone, static, DB-connection-free gate script
  (`MessagesSchemaProvisioningTest.php`, `tsk-001`), and `application/tests/infra/`
  holds a second (`FrozenRuntimeContainerTest.php`, `tsk-002`, does live Docker
  verification where available); neither is a PHPUnit suite and neither covers
  the sign/list flow (the characterization net, `tsk-003` in the concrete
  `.ptah/tasks/` queue — see `legacy_debt.md` DEBT-10) → test coverage remains
  effectively zero for product behavior.
- `Dockerfile` + `docker-compose.yml` (`tsk-002`) pin the exact legacy runtime
  this app already assumed (PHP 5.6, MySQL 5.7, same-host `mysqli` "localhost"
  socket topology) as a reproducible container — a baseline for
  characterization, not an upgrade; see `legacy_debt.md` DEBT-3 (resolved) and
  DEBT-7 (`composer.json` itself is still unresolvable; PHPUnit availability
  is mitigated via a container-pinned binary + committed `composer.lock`,
  live-verified — see `legacy_debt.md` DEBT-7).
- `application/config/autoload.php:61,92` → `database` library and `url` helper are
  globally autoloaded; every request opens a MySQL connection before routing.
- No `.github/workflows` / CI config: the "CI" in the commit history refers to
  CodeIgniter form-validation (`form_validation`), not a continuous-integration
  pipeline. No automated gate exists in the repo today.

## Architecture

Classic CodeIgniter MVC. The request enters `index.php` (front controller),
routes resolve via `application/config/routes.php`
(`default_controller = 'guestbook'`), and the `Guestbook` controller orchestrates
a model and views.

```text
bootstrap (every request) -> autoload.php loads `database` lib + `url` helper
HTTP GET  /            -> Guestbook::index()  -> model::get_messages() -> homepage view
HTTP POST /Guestbook/create -> Guestbook::create() -> form_validation -> model::set_message()
                                                    -> model::get_messages() -> homepage view
```

- **Controller** — `application/controllers/Guestbook.php` (product entry point).
- **Model** — `application/models/Guestbook_messages.php` (CI Active Record on the
  `messages` table).
- **Views** — `application/views/guestbook_homepage.php` composes
  `guestbook_components/form.php` and `guestbook_components/timeline.php`, plus
  `template/{metadata,css,js}.php`.
- **Welcome** — the stock CI demo (`application/controllers/Welcome.php`,
  `views/welcome_message.php`) was unused by the product and has been removed
  (`tsk-010`; see `legacy_debt.md` — Dead / unused code, resolved).

## Boundaries

- **Product vs. vendor** — product code is `application/controllers/Guestbook.php`,
  `application/models/Guestbook_messages.php`, and the `guestbook_*` views.
  `system/` and `user_guide/` are the framework and its docs — treat as vendor.
- **Trust boundary** — untrusted input (`name`, `email`, `message`) crosses from
  the browser into `Guestbook::create()`. Validation/sanitization happens there
  via `form_validation`; there is **no output-encoding boundary** in the timeline
  view (see `legacy_debt.md`).
- **Persistence boundary** — the controller reaches storage only through the
  model, but the model binds directly to CI Active Record (`$this->db`), so there
  is no swappable repository port yet.

## Conventions observed

- CI naming: singular controller class, `Model` suffix, `form_*` helpers in views.
- Validation rules are declared inline in the controller, not centralized.
- No dependency injection; `form`/`form_validation`/`security` load on demand via
  `$this->load`, but the `database` library and `url` helper are globally
  autoloaded (`application/config/autoload.php:61,92`) onto the CI singleton — an
  implicit global that the model's `$this->db` depends on (see BUG-3 / `#model-ctor`).
- Front-end validation mirrors server rules through `data-rule-*` HTML attributes
  consumed by jQuery Validate (`js/plugins/validation/`).

## Target Architecture (Stage 2 — Design proposal, `pending-validation`)

**Direction: modular monolith on CodeIgniter 3.1.5, evolved by Strangler Fig.**
The framework is **retained, not replaced** — the intent explicitly forbids a
rewrite and the audit shows the risk (zero tests, EOL runtime) makes a rewrite
reckless. We keep classic CI MVC and introduce *seams* around the hardening
concerns so behavior can be pinned first and changed safely behind each seam.
Each seam below is a boundary the software-developer-worker must not cross without
going through the interface.

### Seam 1 — GuestbookRepository persistence port (STR-1 / DEBT-1)

- **Boundary.** A `GuestbookRepository` interface (domain-owned) with two
  operations: `all()` (list newest-first) and `add(entry)` (persist a validated
  submission). The controller depends on the **interface**, never on `$this->db`.
- **First adapter.** The existing CI Active Record model
  (`Guestbook_messages`) becomes the first `CiActiveRecordGuestbookRepository`
  adapter behind the port. Active Record stays; it is now *swappable*.
- **Rule imposed.** No `$this->db` in `application/controllers/`. Reads and writes
  route through the port only. (standards.md: *Persistence boundary*.)
- **Rationale.** Isolates storage so it can be re-platformed and unit-tested in
  isolation; it is also the seam the spam-scoring service (STR-3) composes behind.
- Tracked by `tsk-003`; must land **after** the characterization net (tsk-002).

### Seam 2 — Output-encoding boundary in the timeline view (STR-2 / SEC-1)

- **Boundary.** The timeline view (`guestbook_components/timeline.php`) is the
  **single chokepoint** for output encoding. Every echoed user-controlled value
  (`name`, `email`, `message`) passes through CI's `html_escape()` before it
  reaches the DOM.
- **Rule imposed.** No raw `echo` of a user value in any view — `html_escape()` on
  every dynamic field. (standards.md: *Output encoding*.)
- **Rationale.** Input-side `xss_clean`/`strip_tags` is known-bypassable and is not
  a substitute for contextual output escaping. Escaping at the view is the correct,
  durable defense against stored XSS and lives at one auditable location.
- **Behavior-changing.** Emits different bytes — must land **after** tsk-002.

### Seam 3 — CSRF via CI's native mechanism (SEC-4)

- **Boundary.** Enable CodeIgniter's built-in `csrf_protection` in `config.php`;
  `form_open()` already emits the hidden token field, so the create form
  (`guestbook_components/form.php`) gains a token with no bespoke code. CI rejects
  a POST to `Guestbook/create` that lacks a valid token before the controller runs.
- **Rule imposed.** State-changing POSTs carry and verify the native CSRF token.
  (standards.md: *CSRF protection*.) No hand-rolled CSRF scheme.
- **Behavior-changing.** A tokenless POST now fails — must land **after** tsk-002
  (the characterization net records the *current* tokenless-accept behavior first).

### Seam 4 — Env-driven secret management (SEC-2 / SEC-3, and SEC-5)

- **Boundary.** DB credentials (`database.php`), the `encryption_key`
  (`config.php`), and `ENVIRONMENT` resolve from the **environment**
  (`getenv()` / a non-committed `.env`), never from tracked source. Config files
  read env with a safe fallback and hold **no** secret literals.
- **CI-3-compatible transitional approach.** CI 3.x has no native dotenv loader;
  keep it framework-compatible by reading `getenv()` in the config files (optionally
  populated from a git-ignored `.env` via a tiny bootstrap include or the web
  server / container env). No new framework, no Composer runtime dependency
  required. The committed secrets (`Start123!`, the static key) are **rotated and
  purged from history**, not merely edited — editing leaves them in git history.
- **Rule imposed.** No credential or key literal in source; env-driven config.
  (standards.md: *Secret management*.)
- **Related.** SEC-5 (`db_debug` / `ENVIRONMENT` default) is governed by the same
  env boundary: production is asserted via `CI_ENV`, so error output is suppressed.

### Stabilization ordering constraint (hard dependency)

The characterization safety net **MUST** exist before any behavior-changing
security fix. Sequencing is a design constraint, not a preference:

```text
tsk-001 (frozen env)
   -> characterization net  [Seam-independent; freezes CURRENT behavior + bugs]
        -> Seam 2 output encoding (SEC-1)   [changes rendered bytes]
        -> Seam 3 CSRF (SEC-4)              [changes POST acceptance]
        -> Seam 1 repository port (STR-1)   [behavior-preserving refactor; tsk-003]
   Seam 4 secret management (SEC-2/3)       [config/infra; no rendered-behavior
                                             change — may proceed in parallel once
                                             the frozen env resolves config from env]
```

Rationale: output encoding and CSRF change observable output; without the frozen
net (tsk-002) recording the *pre-fix* behavior, a regression is invisible. The
repository port (Seam 1) is behavior-preserving and is verified by keeping the same
net green across the adapter swap. Secret management (Seam 4) touches configuration
and history, not rendered behavior, so it is not gated behind the net — but it does
require the frozen environment to resolve config from `getenv()`.

### What is explicitly NOT changing

- **No framework replacement.** CI 3.1.5 stays. EOL risk (DEBT-4) is acknowledged
  and deferred; a runtime/framework migration is out of this design's scope.
- **No new persistence engine.** MySQL via `mysqli` stays; the port makes a future
  swap *possible*, it does not perform one here.
- **No bespoke security primitives.** CSRF and encoding use native CI mechanisms.

### Standards imposed by this design

See `standards.md`. This design confirms five `Pending` rules — *Output encoding*,
*CSRF protection*, *Secret management*, *Persistence boundary*, *Characterization
tests* — and the sequencing that governs when each may be promoted to a blocking
gate. Promotion happens only on CTO approval of this design.
