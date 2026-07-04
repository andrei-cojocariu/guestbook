# Standard Matrix — Guestbook

**Mode: Transitional (stabilization baseline).**

The stack is a legacy CodeIgniter 3.1.5 monolith on PHP `>=5.3.7` with zero test
coverage and no dependency isolation. A mature ceiling (PHPStan L8, hexagonal
isolation) cannot be enforced against framework-coupled PHP 5.x code without
first stabilizing it. The matrix below targets **stabilization**, not perfection:
get behavior under characterization tests, establish a persistence boundary, and
pin the runtime. Every rule that would gate as-yet-unwritten scaffolding is marked
`Pending` and must pass **human CTO review** before it becomes a blocking gate.

## Matrix

```markdown
| Rule | Target | Enforcement | Status |
| :--- | :--- | :--- | :--- |
| Reproducible runtime | Pin PHP 5.6 + MySQL 5.7 in a container image | CI blocking | Pending |
| Dependency manifest | Add app-level composer + lockfile; pin front-end libs | CI warning | Pending |
| Characterization tests | Cover sign/list flow (input->output, bugs frozen) | CI warning | Pending |
| Static analysis | PHPStan L4 baseline, ratchet upward | CI warning | Pending |
| Coding style | PSR-12 via php-cs-fixer on product code only | CI warning | Pending |
| Output encoding | html_escape() every echoed user value in views | CI blocking | Pending |
| CSRF protection | Enable csrf_protection + token on the guestbook form | CI blocking | Pending |
| Secret management | No credentials in source; env-driven config | CI blocking | Pending |
| Persistence boundary | GuestbookRepository port; no $this->db in controller | Manual review | Pending |
| No direct filesystem writes | Route persistence through the repository port only | Manual review | Active |
```

## Stage 2 (Design) confirmation — `pending-validation`

The Stage-2 architecture in `system.md` (modular monolith / Strangler Fig on CI
3.x) **confirms** five of the `Pending` rules above as the strategic constraints its
seams impose. They remain **`Pending`** — this design does not promote anything to
Active; that is the human CTO's decision on approving the design.

| Rule (confirmed by design) | Seam / finding | Intended enforcement on promotion |
| :--- | :--- | :--- |
| Output encoding | Seam 2 / SEC-1, STR-2 | CI blocking — grep-gate for raw `echo` of user values in views; `html_escape()` required |
| CSRF protection | Seam 3 / SEC-4 | CI blocking — `csrf_protection = TRUE`; tokenless POST to `Guestbook/create` must 403 |
| Secret management | Seam 4 / SEC-2, SEC-3, SEC-5 | CI blocking — no secret literals in tracked config; secrets resolve via `getenv()` |
| Persistence boundary | Seam 1 / STR-1, DEBT-1 | Manual review → blocking once port exists — no `$this->db` in controllers |
| Characterization tests | net / DEBT-2 | CI warning → blocking — sign/list flow frozen (bugs included) before behavior-changing fixes |

### Promotion sequencing (hard constraint)

- The **Characterization tests** rule must go green **before** *Output encoding* and
  *CSRF protection* may be promoted to blocking — those two change observable output
  and would otherwise fail the not-yet-written net. This is the same sequence the
  ordering constraint in `system.md` records: `characterize -> encode/CSRF`.
- **Persistence boundary** promotes to blocking only **after** the port exists and
  the characterization net stays green across the adapter swap (STR-1 / tsk-003).
- **Secret management** is config/infra, not rendered behavior, so it is **not**
  gated behind the net; it may be promoted once secrets are externalized and the
  committed credentials/key are rotated and purged from history.

Feature contracts under `features/` express each rule as testable Gherkin with a
1:1 scenario→intended-test mapping; those are the acceptance surface a rule gates.

## Rationale and CTO review notes

- **Runtime / manifest / tests are `Pending`** because the scaffolding they gate
  (container image, lockfile, PHPUnit suite) does not exist yet. They trace to
  `tsk-001` (freeze), `tsk-002` (characterize), and must not block PRs until the
  baseline artifacts land. CTO decision needed: which become blocking and when.
- **Static analysis is `Pending` at L4, not L8.** PHP 5.x framework coupling and
  CI magic accessors make L8 unreachable without a rewrite. Recommend a ratchet:
  start at L4 on `application/controllers` + `application/models`, raise per PR.
- **Output encoding and CSRF are security gates** but marked `Pending` because
  turning them on today changes rendered output and would fail the not-yet-written
  characterization tests. Sequence: characterize first (`tsk-002`), then enforce.
  These are the highest-priority `Pending` rules for CTO sign-off.
- **Persistence boundary** is the Strangler Fig seam tracked by `tsk-003`; keep it
  `Manual review` until the port exists, then promote to blocking.

## Out of scope (vendor)

`system/` and `user_guide/` are the CodeIgniter framework and its documentation.
They are excluded from every rule above; do not lint, refactor, or gate them.
