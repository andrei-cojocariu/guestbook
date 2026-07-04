# Audit Index — Guestbook

Feature-to-file map. Regenerated each audit run; the authoritative cross-reference
downstream workers navigate. Stack: CodeIgniter 3.1.5 on PHP `>=5.3.7`, MySQL
(`mysqli`). Mode: **transitional**. See `system.md`, `standards.md`,
`legacy_debt.md`.

## Features

| Feature | Doc | Status | Implemented by |
| :--- | :--- | :--- | :--- |
| Submit a guestbook message | `features/message-submission.md` | live | controller, form view, model, routes |
| Render the message timeline | `features/timeline-rendering.md` | live | controller, model, timeline view, homepage |
| Persist guestbook messages | `features/message-persistence.md` | live | model, database config, autoload, schema |
| Provision the messages schema | `features/schema-provisioning.md` | live (tsk-001, static gate) | schema DDL, static test |
| Env-driven secret management | `features/secret-management.md` | planned | database.php, config.php, index.php |

## Files

| File | Doc | Part of |
| :--- | :--- | :--- |
| `index.php` | `files/index.php.md` | submission, timeline, secret-management |
| `application/controllers/Guestbook.php` | `files/application/controllers/Guestbook.php.md` | submission, timeline |
| `application/models/Guestbook_messages.php` | `files/application/models/Guestbook_messages.php.md` | persistence, submission, timeline |
| `application/views/guestbook_homepage.php` | `files/application/views/guestbook_homepage.php.md` | submission, timeline |
| `application/views/guestbook_components/form.php` | `files/application/views/guestbook_components/form.php.md` | submission |
| `application/views/guestbook_components/timeline.php` | `files/application/views/guestbook_components/timeline.php.md` | timeline |
| `application/config/database.php` | `files/application/config/database.php.md` | persistence, secret-management |
| `application/config/config.php` | `files/application/config/config.php.md` | submission, secret-management |
| `application/config/routes.php` | `files/application/config/routes.php.md` | submission, timeline |
| `application/config/autoload.php` | `files/application/config/autoload.php.md` | persistence, submission, timeline |
| `schema/messages.sql` | `files/schema/messages.sql.md` | persistence, schema-provisioning |
| `application/tests/schema/MessagesSchemaProvisioningTest.php` | `files/application/tests/schema/MessagesSchemaProvisioningTest.php.md` | schema-provisioning, persistence |

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
| DEBT-3 | `#composer-manifest-unresolvable` | High |
| DEBT-4 | `#eol-framework` | Medium |
| DEBT-5 | `#no-ci-pipeline` | Medium |
| DEBT-6 | `#no-reproducible-env` | Medium |

## Strangler Fig seams

| ID | Seam | Anchor |
| :--- | :--- | :--- |
| STR-1 | GuestbookRepository persistence port | `#active-record-coupling` |
| STR-2 | Output-encoding boundary in timeline view | `#stored-xss` |
| STR-3 | Validation/sanitization guard service | (rules inlined in `Guestbook::create()`) |

## Rebuild note

This run is a **full rebuild** from on-disk code. The current tree contains no
Docker/compose, no frozen-runtime or CSRF tests, no spam filter, and the secrets
are still hardcoded — so those artifacts are intentionally absent from this KB.
Any downstream doc that references them is stale relative to this rebuild.
