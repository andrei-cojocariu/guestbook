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
| Characterization net (sign/list) | `features/characterization-baseline.md` | planned (DEBT-2 / tsk-002) | controller, model, views |
| Filter spam from submissions | `features/spam-filter.md` | planned | — (tsk-004) |

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
| DEBT-3 | `#no-reproducible-env` | Medium |
| DEBT-4 | `#eol-framework` | Medium |
| DEBT-6 | `#no-ci-pipeline` | Medium |
| DEAD-1 | `#dead--unused-code` | Resolved (`tsk-010`) |

## Strangler Fig seams

| ID | Seam | Tracked by |
| :--- | :--- | :--- |
| STR-1 | GuestbookRepository persistence port | tsk-003 (Seam 1) |
| STR-2 | Output-encoding boundary in timeline view | Seam 2 (post-tsk-002) |
| STR-3 | Validation/sanitization + spam-scoring service | tsk-004 |

## Stage 2 (Design) status

`design_status: pending-validation` (see `system.md`). The Stage-2 architecture
(modular monolith / Strangler Fig on CI 3.x, four seams) and the five confirmed
`Pending` standards await human CTO sign-off before Stage 3 (product-owner-worker)
may generate tasks.

## Task queue traceability

The seed queue in `.ptah/tasks/` traces to this audit: `tsk-001` → `#no-reproducible-env`,
`tsk-002` → `#no-test-coverage`, `tsk-003` → `#active-record-coupling` / STR-1,
`tsk-004` → `features/spam-filter.md` / STR-3, `tsk-010` → `#dead--unused-code`
(resolved — Welcome demo removed).

`tsk-001` DDL delivered: `schema/messages.sql` has landed (forward-only,
idempotent-by-construction, matches the model's insert shape and the
database.php charset/collation) — see `files/schema/messages.sql.md`. This is
an artifact-only delivery verified statically; no live database exists yet,
so forward-apply / idempotent re-apply / rollback execution are
`[deferred: tsk-002]`. `#no-reproducible-env` remains open pending the
container/runtime pinning that lets this DDL actually run.
