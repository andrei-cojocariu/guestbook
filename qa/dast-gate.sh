#!/bin/sh
# Security (DAST) gate for guestbook2 (pipeline v3.0.0).
#
# Runs the OWASP ZAP baseline scan with the committed rule-promotion config
# (zap-rules.conf), which promotes the missing-security-header / insecure-cookie
# passive rules from ZAP's default WARN to FAIL. A bare `zap-baseline.py` leaves
# every passive rule at WARN, so "zero FAIL" is structurally untrippable (proven
# vacuous 2026-07-12). This wrapper fails the gate ONLY on a real FAIL — it is
# NOT `|| true`: because the security rules are promoted to FAIL, a missing header
# or an insecure cookie yields ZAP exit 1 and turns the gate red. ZAP exit 2
# (informational passive WARN only — e.g. SPA detection) is tolerated, matching
# the "zero FAIL; zero unwaived WARN" threshold (structural WARNs are IGNOREd
# with rationale in zap-rules.conf).
#
# Usage:  qa/dast-gate.sh [target]     target defaults to http://localhost:8080
# Exit:   0 = zero FAIL, 1 = a promoted security rule FAILED, 3 = ZAP run error.
set -u

TARGET="${1:-http://localhost:8080}"
DIR="$(cd "$(dirname "$0")" && pwd)"

docker run --rm --network host -v "$DIR":/zap/wrk:rw \
    ghcr.io/zaproxy/zaproxy:stable \
    zap-baseline.py -t "$TARGET" -c zap-rules.conf -w zap-baseline.md
rc=$?

case "$rc" in
    0) echo "DAST PASS: zero FAIL, zero unwaived WARN"; exit 0 ;;
    2) echo "DAST PASS: zero FAIL (informational WARN only, tolerated)"; exit 0 ;;
    1) echo "DAST FAIL: a promoted security rule fired (missing header / insecure cookie)"; exit 1 ;;
    *) echo "DAST ERROR: ZAP failed to run (rc=$rc)"; exit 3 ;;
esac
