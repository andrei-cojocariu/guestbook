<?php

declare(strict_types=1);

/**
 * Coverage floor enforcement for the guestbook2 coverage gate (pipeline v3.0.0).
 *
 * `phpunit --coverage-text` only PRINTS the line percentage and always exits 0,
 * so a "coverage >= 80%" threshold wired to it enforces nothing (proven vacuous
 * 2026-07-12: coverage fell to 5.70% and the gate still exited 0). This script
 * parses the Clover report PHPUnit emits (`--coverage-clover coverage.xml`) and
 * exits nonzero when line coverage is below the floor, giving the gate teeth.
 *
 * Usage:  php tests/enforce_coverage.php [coverage.xml] [floor]
 *   floor defaults to the COVERAGE_MIN env var, then to 80.
 * Exit:   0 = at/above floor, 1 = below floor, 2 = report missing/unreadable.
 */

$reportPath = $argv[1] ?? 'coverage.xml';
$floor = (float) ($argv[2] ?? getenv('COVERAGE_MIN') ?: '80');

if (! is_file($reportPath)) {
    fwrite(STDERR, "enforce_coverage: clover report not found at {$reportPath}\n");
    exit(2);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($reportPath);
if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "enforce_coverage: could not parse <project><metrics> from {$reportPath}\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "enforce_coverage: no statements measured — is the coverage <source> scope empty?\n");
    exit(2);
}

$rate = $covered / $statements * 100.0;
printf("Line coverage: %.2f%% (%d/%d statements) — floor %.2f%%\n", $rate, $covered, $statements, $floor);

// 1e-9 tolerance so an exact-floor value (e.g. 80.00%) passes despite float noise.
if ($rate + 1e-9 < $floor) {
    fwrite(STDERR, sprintf("FAIL: line coverage %.2f%% is below the %.2f%% floor\n", $rate, $floor));
    exit(1);
}

echo "PASS: coverage floor satisfied\n";
exit(0);
