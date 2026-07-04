# Audit Index — Guestbook

Feature-to-file map. Regenerated each audit run; the authoritative cross-reference
downstream workers navigate. Stack: CodeIgniter 3.1.5 on PHP `>=5.3.7`, MySQL
(`mysqli`). Mode: **transitional**. See `system.md`, `standards.md`,
`legacy_debt.md`.

## Features

| Feature | Doc | Status | Implemented by |
| :--- | :--- | :--- | :--- |
| Submit a guestbook message | `features/message-submission.md` | live (+ CSRF hardening, SEC-4) | controller, form view, model, config |
| Render the message timeline | `features/timeline-rendering.md` | live (+ output-encoding hardening, SEC-1) | controller, model, timeline view, homepage |
| Persist guestbook messages | `features/message-persistence.md` | live (+ repository port, STR-1) | model |
| Env-driven secret management | `features/secret-management.md` | planned (SEC-2/3/5) | database.php, config.php, index.php |
| Characterization net (sign/list) | `features/characterization-baseline.md` | planned (DEBT-2 / tsk-003; frozen runtime prerequisite tsk-002 delivered) | controller, model, views |
| Filter spam from submissions | `features/spam-filter.md` | planned | — (`tsk-009`, per the concrete `.ptah/tasks/` queue; `features/spam-filter.md` still cites `tsk-004` — see `legacy_debt.md` DEBT-10) |

## Files

| File | Doc | Part of |
| :--- | :--- | :--- |
| `application/controllers/Guestbook.php` | `files/application/controllers/Guestbook.php.md` | submission, timeline |
| `application/models/Guestbook_messages.php` | `files/application/models/Guestbook_messages.php.md` | persistence, submission, timeline |
| `application/views/guestbook_homepage.php` | `files/application/views/guestbook_homepage.php.md` | submission, timeline |
| `application/views/guestbook_components/form.php` | `files/application/views/guestbook_components/form.php.md` | submission |
| `application/views/guestbook_components/timeline.php` | `files/application/views/guestbook_components/timeline.php.md` | timeline |
| `application/config/database.php` | `files/application/config/database.php.md` | persistence |
| `application/config/config.php` | `files/application/config/config.php.md` | submission |
| `application/config/routes.php` | `files/application/config/routes.php.md` | submission, timeline |
| `application/config/autoload.php` | `files/application/config/autoload.php.md` | persistence, submission, timeline |
| `application/tests/schema/MessagesSchemaProvisioningTest.php` | `files/application/tests/schema/MessagesSchemaProvisioningTest.php.md` | persistence (tsk-001 schema gate; no dedicated feature scenario) |
| `schema/messages.sql` | `files/schema/messages.sql.md` | persistence (tsk-001 versioned DDL) |
| `Dockerfile` | `files/Dockerfile.md` | characterization-baseline (tsk-002 frozen runtime) |
| `docker-compose.yml` | `files/docker-compose.yml.md` | characterization-baseline (tsk-002 frozen runtime) |
| `.dockerignore` | `files/.dockerignore.md` | characterization-baseline (tsk-002 build-context scoping) |
| `application/tests/infra/FrozenRuntimeContainerTest.php` | `files/application/tests/infra/FrozenRuntimeContainerTest.php.md` | characterization-baseline (tsk-002 container gate; no dedicated feature scenario) |

## Debt anchors (see legacy_debt.md)

| ID | Anchor | Severity |
| :--- | :--- | :--- |
| SEC-1 | `#stored-xss` | Critical |
| SEC-2 | `#hardcoded-db-credentials` | Critical |
| SEC-3 | `#hardcoded-encryption-key` | Critical |
| SEC-4 | `#csrf-disabled` | Critical |
| SEC-5 | `#db-debug-leak` | High |
| BUG-1 | `#timeline-time-bug` | High |
| BUG-2 | `#silent-insert-success` | High |
| BUG-3 | `#model-ctor` | High |
| DEBT-1 | `#active-record-coupling` | Medium |
| DEBT-2 | `#no-test-coverage` | Medium |
| DEBT-3 | `#no-reproducible-env` | Resolved (`tsk-001` + `tsk-002`) |
| DEBT-4 | `#eol-framework` | Medium |
| DEBT-6 | `#no-ci-pipeline` | Medium |
| DEBT-7 | `#composer-manifest-unresolvable` | High (blocks tsk-003 installing PHPUnit) |
| DEBT-8 | `#tsk001-script-php7-syntax` | Low (unwired; informs tsk-003 tooling choice) |
| DEBT-9 | `#tsk002-self-check-allowlist-gap` | Resolved (rejection-1 retry) |
| DEBT-10 | `#task-numbering-scheme-mismatch` | Low (documentation only) |
| DEAD-1 | `#dead--unused-code` | Resolved (`tsk-010`) |

## Strangler Fig seams

| ID | Seam | Tracked by |
| :--- | :--- | :--- |
| STR-1 | GuestbookRepository persistence port | `tsk-007` (Seam 1; per the concrete `.ptah/tasks/` queue — see `legacy_debt.md` DEBT-10) |
| STR-2 | Output-encoding boundary in timeline view | Seam 2 (post-`tsk-003`, the characterization net) |
| STR-3 | Validation/sanitization + spam-scoring service | `tsk-008` (guard) / `tsk-009` (spam feature) |

## Stage 2 (Design) status

`system.md`'s header comment currently reads `design_status: design-validated`.
This section previously said `pending-validation`; that is now stale — the
concrete `.ptah/tasks/` queue (11 tasks) already exists and several have
executed and merged (`tsk-001`, `tsk-010`; `tsk-002` landing on this pass),
which could only happen after Stage 3 (product-owner-worker) generated the
queue, which in turn requires the Stage 2 design's CTO sign-off gate to have
already passed. Flag for the human: confirm this reading is correct, since
`system.md`'s own inline comment still warns "Do NOT set design-validated
here — only the CTO does that on approval," an apparent leftover from before
that approval.

## Task queue traceability

The seed queue in `.ptah/tasks/` traces to this audit — corrected here against
`.ptah/tasks/INDEX.md` (ground truth; see `legacy_debt.md` DEBT-10 for why this
differs from older prose elsewhere in `system.md`/`standards.md`):

- `tsk-001` → `#no-reproducible-env` (schema/DDL half; done)
- `tsk-002` → `#no-reproducible-env` (frozen-container half; done, this pass)
- `tsk-003` → `#no-test-coverage` (characterization net; unblocked, not started)
- `tsk-007` → `#active-record-coupling` / STR-1 (repository port)
- `tsk-008` / `tsk-009` → `features/spam-filter.md` / STR-3 (guard, then spam feature)
- `tsk-010` → `#dead--unused-code` (resolved — Welcome demo removed)

`tsk-001` DDL + `tsk-002` frozen runtime delivered: `schema/messages.sql`
(forward-only, idempotent-by-construction) is now seeded into a live,
pinned `php:5.6.40-apache` + `mysql:5.7.44` container on first boot — see
`files/schema/messages.sql.md`, `files/Dockerfile.md`,
`files/docker-compose.yml.md`. Forward-apply against an empty database, a
live insert with the model's exact shape, idempotent re-apply against a
populated table, and rollback are all verified live (re-run in this
docs-sync pass, via the committed
`application/tests/infra/FrozenRuntimeContainerTest.php`) — see
`legacy_debt.md` DEBT-3. `#no-reproducible-env` is resolved;
`#no-test-coverage` (the characterization net itself, `tsk-003`) remains open.
