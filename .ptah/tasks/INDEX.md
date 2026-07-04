# Execution Ledger — Guestbook

Master DAG for the CodeIgniter 3.1.5 hardening, generated from the CTO-validated
Stage 2 design (`../audit/system.md`, `design_status: design-validated`) and its four
seams. Every task traces to an audit finding or a `features/` BDD contract. All tasks
start `pending`/`blocked`; nothing is `active`. No branch is cut until GATE 1 human
approval. Priority order is strict: **all P0 stabilization/security work outranks the
P1 feature work below it**, enforced structurally through `depends_on`.

## Task DAG

```markdown
| Task | Type | Priority | Sev | Status | Depends on |
| :--- | :--- | :--- | :--- | :--- | :--- |
| tsk-001 | chore | P0-Critical | high | done | — |
| tsk-002 | chore | P0-Critical | medium | audit-failed | tsk-001 |
| tsk-003 | stabilize | P0-Critical | high | blocked | tsk-002 |
| tsk-004 | chore | P0-Critical | high | blocked | tsk-002 |
| tsk-005 | decouple | P0-Critical | high | blocked | tsk-003 |
| tsk-006 | chore | P0-Critical | high | blocked | tsk-003 |
| tsk-007 | decouple | P2-Debt | medium | blocked | tsk-003 |
| tsk-008 | decouple | P2-Debt | medium | blocked | tsk-007 |
| tsk-009 | feature | P1-Value | medium | blocked | tsk-008 |
| tsk-010 | chore | P3-Backlog | low | done | — |
| tsk-011 | chore | P3-Backlog | low | blocked | tsk-003 |
```

- **tsk-001** (data-engineer: `messages` schema DDL) has no dependency — the
  `CREATE TABLE` the frozen container and net run against. Migrations are a
  data-engineer-worker task; software-developer-workers must not run DDL.
- **tsk-002** (freeze runtime container) cannot start until **tsk-001** is `done` —
  the container seeds the schema artifact on boot.
- **tsk-003** (characterization net) cannot start until **tsk-002** is `done` — it
  black-boxes the frozen container. This net is the hard gate: it MUST be green
  before any behavior-changing security fix.
- **tsk-004** (env secret management, Seam 4) cannot start until **tsk-002** is
  `done`. It is config/infra with no rendered-behavior change, so it is **not** gated
  behind the net — it needs only the frozen env to resolve `getenv()`, and runs in
  parallel with tsk-003.
- **tsk-005** (output encoding, Seam 2) and **tsk-006** (CSRF, Seam 3) cannot start
  until **tsk-003** is `done` — both change observable output/acceptance and must be
  made against the recorded baseline. This is the design's hard stabilization
  ordering constraint (`characterize -> encode/CSRF`).
- **tsk-007** (repository port, Seam 1) cannot start until **tsk-003** is `done` — a
  behavior-preserving refactor, verified by keeping the net green across the swap.
- **tsk-008** (submission-guard validation service, STR-3) cannot start until
  **tsk-007** is `done` — the guard composes behind the repository port.
- **tsk-009** (spam filter feature) cannot start until **tsk-008** is `done` — spam
  scoring composes inside the guard. This is the only P1 feature; it is structurally
  gated behind all P0 stabilization/security work.
- **tsk-010** (remove dead Welcome demo) has no dependency — isolated, unreachable
  code; safe to run at any time.
- **tsk-011** (form route/typo polish) cannot start until **tsk-003** is `done` —
  it changes rendered copy, so it lands after the net freezes current bytes.

The graph is acyclic. Shared-file coordination note: **tsk-004** and **tsk-006** both
edit `application/config/config.php` (distant regions — `encryption_key` vs
`csrf_protection`); if both are in flight, serialize the merge.

## Critical path

The longest dependency chain (6 tasks) is:

```text
tsk-001 -> tsk-002 -> tsk-003 -> tsk-007 -> tsk-008 -> tsk-009
(schema -> freeze -> characterize -> repo port -> guard -> spam feature)
```

The P0 stabilization trunk `tsk-001 -> tsk-002 -> tsk-003` is the pacing constraint:
nothing behavior-changing can proceed until the net (tsk-003) is green.

## Concurrency

```markdown
concurrency_limit: 6
```

The `concurrency_limit` is the widest antichain of independently dispatchable tasks —
the safe upper bound on simultaneous software-developer-worker branches. It is a
ceiling, not a target; the orchestrator runs fewer, filling P0 slots before P1/P2/P3.

- **Ready now (no unmet dependencies):** `tsk-001`, `tsk-010` (2 tasks). Everything
  else is `blocked` on an unmet `depends_on`.
- **Serial trunk (no parallelism possible):** `tsk-001 -> tsk-002 -> tsk-003` — each
  strictly gates the next.
- **Peak parallel fan-out (after tsk-002 and tsk-003 are `done`):** up to 6 mutually
  independent tasks may be ready at once — `tsk-004`, `tsk-005`, `tsk-006`, `tsk-007`,
  `tsk-010`, `tsk-011`. Respecting priority, the P0 security seams fill slots first:
  `tsk-004` (secrets), `tsk-005` (output encoding), `tsk-006` (CSRF) run in parallel;
  then the P2 refactor `tsk-007`; then the P3 chores `tsk-010`/`tsk-011`.
- **Serialized tail:** `tsk-007 -> tsk-008 -> tsk-009` — the repository port, guard
  extraction, and spam feature are a single dependency chain and cannot overlap.

## Coverage notes — audit findings not turned into tasks

- **BUG-1** (`#timeline-time-bug`), **BUG-2** (`#silent-insert-success`),
  **BUG-3** (`#model-ctor`): the validated design explicitly **freezes** these as
  current behavior and does not fix them. They are characterized by tsk-003 (and
  preserved by tsk-007), so there is deliberately **no fix task**. Flag for the human:
  if any of these should be fixed, a follow-up task is required — the design defers
  them.
- **DEBT-4** (`#eol-framework`, EOL CI 3.1.5 / PHP 5.x): explicitly **out of scope /
  deferred** by the design ("no framework replacement"). No task; a runtime migration
  is a separate future initiative the human should track as accepted EOL risk.
- **Dependency manifest** (app-level composer + lockfile, front-end lib pinning) and
  **PSR-12 / PHPStan L4** standards rows are `Pending` in `standards.md` but the
  design creates no dedicated seam for them. Linter/analyzer wiring is folded into the
  container hooks (tsk-002) and referenced by task TACs; promoting these rules to
  blocking is a CTO decision, not a task here. Noted as a partial-coverage gap.
