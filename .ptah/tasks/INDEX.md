# Execution Ledger — Guestbook

Master DAG for the CodeIgniter 3.1.5 hardening, generated from the CTO-validated
Stage 2 design (`../audit/system.md`, `design_status: design-validated`) and the
Strangler seams in `../audit/legacy_debt.md`. Every task traces to an audit finding or
a `features/` BDD contract. Statuses are reconciled against main-task: **tsk-001** and
**tsk-010** are merged (`done`); all remaining tasks start `pending`/`blocked`, and
`blocked` derives strictly from an unmet `depends_on`. Nothing is `active`. No branch is
cut until the GATE 1 human approval. Priority order is strict: **all P0
stabilization/security work outranks the P1/P2 work below it**, enforced structurally
through `depends_on`.

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
| tsk-010 | chore | P3-Backlog | low | done | — |
```

- **tsk-001** (`messages` schema DDL) is `done` and merged — the `CREATE TABLE` the
  frozen container and net run against. Schema/migration work is a data-engineer-worker
  task; software-developer-workers must not run DDL.
- **tsk-002** (freeze runtime container) depends on **tsk-001**; that edge is satisfied
  (tsk-001 is `done`), so tsk-002 is `pending` and dispatchable now. It seeds the schema
  and makes the toolchain runnable.
- **tsk-003** (characterization net) cannot start until **tsk-002** is `done` — it
  black-boxes the frozen container. This net is the hard gate: it MUST be green before
  any behavior-changing security fix.
- **tsk-004** (env secret management) cannot start until **tsk-002** is `done`. It is
  config/infra with no rendered-behavior change, so it is **not** gated behind the net —
  it needs only the frozen env to resolve `getenv()`, and runs in parallel with tsk-003.
- **tsk-005** (output encoding, STR-2) and **tsk-006** (CSRF) cannot start until
  **tsk-003** is `done` — both change observable output/acceptance and must be made
  against the recorded baseline (`characterize -> encode/CSRF`).
- **tsk-007** (repository port, STR-1) cannot start until **tsk-003** is `done` — a
  behavior-preserving refactor, verified by keeping the net green across the swap.
- **tsk-008** (submission-guard validation service, STR-3) cannot start until
  **tsk-007** is `done` — the guard composes behind the repository port.
- **tsk-010** (remove dead Welcome demo) is `done` and merged — isolated, unreachable
  code.

The graph is acyclic. Shared-file coordination note: **tsk-004** and **tsk-006** both
edit `application/config/config.php` (distant regions — `encryption_key` vs
`csrf_protection`); if both are in flight, serialize the merge.

## Critical path

The longest dependency chain (5 nodes) is:

```text
tsk-001 -> tsk-002 -> tsk-003 -> tsk-007 -> tsk-008
(schema -> freeze -> characterize -> repo port -> guard)
```

With tsk-001 already `done`, the live pacing constraint is the P0 stabilization trunk
`tsk-002 -> tsk-003`: nothing behavior-changing can proceed until the net (tsk-003) is
green.

## Concurrency

```markdown
concurrency_limit: 4
```

The `concurrency_limit` is the widest antichain of independently dispatchable tasks —
the safe upper bound on simultaneous software-developer-worker branches. It is a
ceiling, not a target; the orchestrator runs fewer, filling P0 slots before P2.

- **Ready now (no unmet dependencies):** `tsk-002` only. `tsk-001` and `tsk-010` are
  already `done`; every other task is `blocked` on an unmet `depends_on`.
- **Serial trunk (no parallelism possible):** `tsk-002 -> tsk-003` — each strictly gates
  the next.
- **Batch A — after tsk-002 is `done`:** `tsk-003` (net) and `tsk-004` (secrets) run in
  parallel (2). Both depend only on tsk-002; tsk-004 is not gated behind the net.
- **Batch B — peak fan-out, after tsk-003 is `done` while tsk-004 may still run:** up to
  4 mutually independent tasks may be ready at once — `tsk-004`, `tsk-005`, `tsk-006`,
  `tsk-007`. Respecting priority, the P0 security seams fill slots first (`tsk-004`
  secrets, `tsk-005` encoding, `tsk-006` CSRF), then the P2 refactor `tsk-007`.
- **Serialized tail:** `tsk-007 -> tsk-008` — the repository port then the guard
  extraction are a single dependency chain and cannot overlap.

## Coverage notes — audit findings not turned into tasks

- **Frozen bugs — BUG-1** ([#timeline-time-bug](../audit/legacy_debt.md#timeline-time-bug)),
  **BUG-2** ([#silent-insert-success](../audit/legacy_debt.md#silent-insert-success)),
  **BUG-3** ([#model-ctor](../audit/legacy_debt.md#model-ctor)): the validated design
  explicitly **freezes** these as current behavior. They are characterized by tsk-003
  (and preserved by tsk-007), so there is deliberately **no fix task**. Flag for the
  human: if any should be fixed, a follow-up task is required — the design defers them.
- **EOL framework** ([#eol-framework](../audit/legacy_debt.md#eol-framework), CI 3.1.5 /
  PHP 5.x): explicitly **out of scope / deferred** by the design ("no framework
  replacement"). No task; tracked as accepted EOL risk for the human.
- **No CI pipeline** ([#no-ci-pipeline](../audit/legacy_debt.md#no-ci-pipeline)): folded
  into tsk-002 (the container exposes `hooks.lint`/`test`/`analyze` entrypoints) rather
  than a standalone task; promoting the `Pending` standards rows (PSR-12, PHPStan ramp,
  secret-scan) to blocking gates is a CTO decision, not a task here. Noted as partial
  coverage.
- **De-scoped prior inventions:** the earlier ledger carried **tsk-009 (spam filter)**
  and **tsk-011 (form/route polish)**. Neither traces to a `features/` BDD contract or a
  design seam — no source anchor exists — so they are **excluded** from this queue per
  the no-invented-work mandate. Their numbers are left void (tsk-010 is merged and must
  not be renumbered). Flag for the human: if a spam-filter feature is wanted, it needs a
  BDD contract authored first; re-queue then.
