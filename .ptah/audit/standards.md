# Standards Matrix — Guestbook

Mode: **Transitional (stabilization baseline)**.

Rationale: the stack is a modern manifest (`composer.json`) pinning an
**end-of-life, unversioned** framework (CodeIgniter 3.1.5 on PHP `>=5.3.7`) with
**no installed toolchain** (`vendor/` absent, `composer_autoload = FALSE`). A
mature ceiling (PHPStan L8 / PSR-12 / hexagonal) cannot be enforced against this
tree today. The matrix below stabilizes first — get code under test, establish
boundaries, pin the environment — and marks every not-yet-enforceable rule
**Pending** for human CTO review before it becomes a blocking gate.

## Matrix

| Rule | Target | Enforcement | Status |
| :--- | :--- | :--- | :--- |
| Read-only audit KB | No product code mutated by stage 1 | Manual review | Active |
| Forward-only DDL | No `ALTER`/`DROP` in `schema/*.sql` | `MessagesSchemaProvisioningTest` (static) | Active |
| Markdown style | One H1, fenced+language, aligned tables | Lint gate on `.ptah/audit/` | Active |
| No new hardcoded secrets | No new credentials/keys in source | Manual review | Active |
| Characterization tests | Cover sign + list flow before refactor | CI warning → blocking | Pending |
| Install & pin toolchain | `composer.lock`; PHPUnit runnable; autoload on | CI blocking | Pending |
| Static analysis | PHPStan L5 → L8 (raise as debt burns down) | CI blocking | Pending |
| Coding standard | PSR-12 via PHP_CodeSniffer | CI warning | Pending |
| Output encoding | All echoed user data HTML-escaped in views | CI blocking after net freezes | Pending |
| CSRF protection | CI native `csrf_protection = TRUE` on POST | CI blocking after net freezes | Pending |
| Secret management | Credentials/keys from env, not source | Manual review → CI secret-scan | Pending |
| Persistence boundary | Repository port around Active Record | Manual review | Pending |
| Pinned reproducible env | Containerized PHP+MySQL for the suite | CI blocking | Pending |
| Supported framework | Migrate off EOL CodeIgniter 3 | Manual review | Pending |

## Enforcement notes

- **Every `Pending` rule is a proposal awaiting CTO sign-off.** None is a gate
  yet. Ordering matters: the characterization net and pinned toolchain/env land
  *first* (they freeze current behavior and make gates runnable); output-encoding
  and CSRF changes alter request/response behavior and therefore land *after* the
  net is green, so a regression is caught, not shipped.
- **Static-analysis ramp** — start PHPStan at a level the untouched legacy tree
  can pass (L5), then raise per module as debt is retired. Enforcing L8 on day
  one against CI3 magic (`$this->db`, `$this->input`, `$this->load`) would flood
  the gate with unactionable errors.
- The `messages` schema gate is Active because it exists and passes today; it is
  static-only (no live DB), so it does not certify runtime DDL behavior.
