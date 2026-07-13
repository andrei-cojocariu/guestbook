#!/bin/sh
# Performance budget gate for guestbook2 (pipeline v3.0.0).
#
# A bare `ab` PRINTS latency but always exits 0, so a "p95 < budget" threshold
# wired to it enforces nothing (proven vacuous 2026-07-12: a 2.2x p95 breach to
# 221ms still exited 0). This wrapper runs `ab`, parses the 95th-percentile
# service time, and exits nonzero when it breaches the budget.
#
# Usage:  qa/perf-budget.sh [url]
#   URL           defaults to http://localhost:8080/
#   PERF_BUDGET_MS defaults to 100 (the manifest budget; clean app ~31ms)
#   PERF_REQUESTS / PERF_CONCURRENCY default to 200 / 10
# Exit:   0 = within budget, 1 = budget breached, 2 = could not measure.
set -eu

URL="${1:-http://localhost:8080/}"
BUDGET_MS="${PERF_BUDGET_MS:-100}"
REQUESTS="${PERF_REQUESTS:-200}"
CONCURRENCY="${PERF_CONCURRENCY:-10}"

out="$(ab -n "$REQUESTS" -c "$CONCURRENCY" "$URL")"
echo "$out"

# ab prints the percentile table as e.g. "  95%     31"
p95="$(printf '%s\n' "$out" | awk '$1 == "95%" { print $2 }')"

if [ -z "$p95" ]; then
    echo "perf-budget: could not parse p95 from ab output" >&2
    exit 2
fi

echo "perf-budget: p95=${p95}ms  budget<${BUDGET_MS}ms"

if [ "$p95" -ge "$BUDGET_MS" ]; then
    echo "FAIL: p95 ${p95}ms breaches the ${BUDGET_MS}ms budget" >&2
    exit 1
fi

echo "PASS: p95 within budget"
