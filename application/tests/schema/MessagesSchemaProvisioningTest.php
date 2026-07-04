<?php
/**
 * MessagesSchemaProvisioningTest — acceptance gate for tsk-001
 * ("Provision the messages table schema for the frozen environment").
 *
 * Standalone PDO-free script: no PHPUnit is installed yet (see hooks.test in
 * .ptah/ptah.yaml — `vendor/bin/phpunit` does not exist), so this runs today
 * as `php application/tests/schema/MessagesSchemaProvisioningTest.php` and is
 * a drop-in TestCase body once the suite lands (tsk-002).
 *
 * Static-only, matching the task's recalibrated Technical Acceptance
 * Criteria: it opens NO database connection and makes NO claim about live
 * DDL execution. One criterion — idempotent re-apply against a *populated*
 * table and actual DB-side population of `received_on` — is out of scope
 * here and is reported as DEFERRED to tsk-002 (the frozen container this
 * schema seeds into), never fabricated as a pass. A prior attempt at this
 * task asserted a live-database transcript without a real test container and
 * was rejected for exactly that; this file does not repeat that mistake.
 *
 * Each test_* method name and its $scenario string restate one bullet of the
 * task's Technical Acceptance Criteria 1:1, so a reviewer can trace pass/fail
 * back to the exact criterion gated.
 */

$root        = dirname(__DIR__, 3);
$schemaPath  = $root . '/schema/messages.sql';
$modelPath   = $root . '/application/models/Guestbook_messages.php';
$dbConfigPath = $root . '/application/config/database.php';

/** @var array<int, array{name:string,scenario:string,status:string,detail:string}> $results */
$results = [];
$failures = 0;

function ptah_assert($cond, $message)
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function ptah_record(array &$results, &$failures, $name, $scenario, callable $fn)
{
    try {
        $fn();
        $results[] = ['name' => $name, 'scenario' => $scenario, 'status' => 'PASS', 'detail' => ''];
    } catch (Throwable $e) {
        $results[] = ['name' => $name, 'scenario' => $scenario, 'status' => 'FAIL', 'detail' => $e->getMessage()];
        $failures++;
    }
}

// ---------------------------------------------------------------------------
// TAC: "schema/messages.sql exists and is committed to the branch;
//       forward-only DDL, no ALTER/DROP"
// ---------------------------------------------------------------------------
ptah_record(
    $results,
    $failures,
    'test_schema_file_exists_committed_and_forward_only',
    'schema/messages.sql exists and is committed to the branch; forward-only DDL, no ALTER/DROP',
    function () use ($schemaPath, $root) {
        ptah_assert(is_file($schemaPath), "schema/messages.sql does not exist at $schemaPath");

        $sql = file_get_contents($schemaPath);
        ptah_assert($sql !== false && trim($sql) !== '', 'schema/messages.sql is empty or unreadable');

        ptah_assert(!preg_match('/\bALTER\s+TABLE\b/i', $sql), 'schema/messages.sql contains an ALTER TABLE statement — must be forward-only');
        ptah_assert(!preg_match('/\bDROP\s+TABLE\b/i', $sql), 'schema/messages.sql contains a DROP TABLE statement — must be forward-only');

        if (is_dir($root . '/.git') || is_file($root . '/.git')) {
            $out = [];
            $code = 0;
            exec('git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch schema/messages.sql 2>&1', $out, $code);
            ptah_assert($code === 0, 'schema/messages.sql is not tracked by git (not committed to the branch): ' . implode("\n", $out));
        }
    }
);

// ---------------------------------------------------------------------------
// TAC: "CREATE TABLE IF NOT EXISTS messages (…) is idempotent by
//       construction, and its columns accept the exact insert shape in
//       application/models/Guestbook_messages.php"
// ---------------------------------------------------------------------------
ptah_record(
    $results,
    $failures,
    'test_create_table_idempotent_by_construction_matches_model_insert_shape',
    'CREATE TABLE IF NOT EXISTS messages (…) is idempotent by construction, and its columns accept the exact insert shape in Guestbook_messages.php',
    function () use ($schemaPath, $modelPath) {
        $sql = file_get_contents($schemaPath);
        ptah_assert(
            (bool) preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+messages\s*\(/i', $sql),
            'DDL does not declare CREATE TABLE IF NOT EXISTS messages (…) — a bare CREATE TABLE would error on re-apply'
        );

        ptah_assert(is_file($modelPath), 'application/models/Guestbook_messages.php not found');
        $model = file_get_contents($modelPath);
        ptah_assert((bool) preg_match('/function\s+set_message\s*\(\s*\)\s*\{(.*?)\n\s*\}/s', $model, $m), 'could not isolate set_message() body in Guestbook_messages.php');

        preg_match_all("/'([a-zA-Z_]+)'\s*=>/", $m[1], $keyMatches);
        $insertKeys = $keyMatches[1];
        ptah_assert(count($insertKeys) > 0, 'no insert keys found in set_message()\'s $data array');
        ptah_assert(
            $insertKeys === ['name', 'email', 'message'],
            'set_message() insert shape changed from the exact name/email/message this DDL was written against: got [' . implode(', ', $insertKeys) . ']'
        );

        foreach ($insertKeys as $key) {
            ptah_assert(
                (bool) preg_match('/\b' . preg_quote($key, '/') . '\s+[A-Z]/i', $sql),
                "column `$key` (used by set_message()'s insert) is not declared in the DDL"
            );
        }
    }
);

// ---------------------------------------------------------------------------
// TAC: "received_on is declared DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
//       (a DB-side default; set_message() omits it) and collation matches
//       application/config/database.php"
// ---------------------------------------------------------------------------
ptah_record(
    $results,
    $failures,
    'test_received_on_is_db_side_default_and_collation_matches_config',
    'received_on is declared DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP (a DB-side default; set_message() omits it) and collation matches application/config/database.php',
    function () use ($schemaPath, $modelPath, $dbConfigPath) {
        $sql = file_get_contents($schemaPath);
        ptah_assert(
            (bool) preg_match('/received_on\s+DATETIME\s+NOT\s+NULL\s+DEFAULT\s+CURRENT_TIMESTAMP/i', $sql),
            'received_on is not declared as DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        $model = file_get_contents($modelPath);
        preg_match('/function\s+set_message\s*\(\s*\)\s*\{(.*?)\n\s*\}/s', $model, $m);
        $setMessageBody = $m[1] ?? '';
        ptah_assert(
            !preg_match("/'received_on'\s*=>/", $setMessageBody),
            'set_message() sets received_on explicitly — it must remain a DB-side default only'
        );

        ptah_assert(is_file($dbConfigPath), 'application/config/database.php not found');
        // Parsed as text (not include()'d) — the file is BASEPATH-guarded and
        // this is a static schema check, not an app bootstrap.
        $configSrc = file_get_contents($dbConfigPath);
        ptah_assert((bool) preg_match("/'char_set'\s*=>\s*'([^']+)'/", $configSrc, $cm), 'char_set not found in application/config/database.php');
        ptah_assert((bool) preg_match("/'dbcollat'\s*=>\s*'([^']+)'/", $configSrc, $lm), 'dbcollat not found in application/config/database.php');
        $charSet = $cm[1];
        $collation = $lm[1];

        ptah_assert(
            (bool) preg_match('/DEFAULT\s+CHARSET\s*=\s*' . preg_quote($charSet, '/') . '\b/i', $sql),
            "DDL DEFAULT CHARSET does not match application/config/database.php's char_set ('$charSet')"
        );
        ptah_assert(
            (bool) preg_match('/COLLATE\s*=\s*' . preg_quote($collation, '/') . '\b/i', $sql),
            "DDL COLLATE does not match application/config/database.php's dbcollat ('$collation')"
        );
    }
);

// ---------------------------------------------------------------------------
// Deferred — reported, never executed here (no live DB / container at this
// stage). See tsk-002.
// ---------------------------------------------------------------------------
$deferred = [
    [
        'name' => 'test_idempotent_reapply_against_populated_table',
        'scenario' => 'Idempotent re-apply against a populated table and actual DB-side population of received_on',
        'reason' => 'no live database / frozen container exists yet at this stage — deferred to tsk-002',
    ],
];

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "MessagesSchemaProvisioningTest — tsk-001 gate (static-only; no live DB)\n";
echo str_repeat('-', 78) . "\n";
foreach ($results as $r) {
    printf("[%s] %s\n      scenario: %s\n", $r['status'], $r['name'], $r['scenario']);
    if ($r['status'] === 'FAIL') {
        printf("      reason:   %s\n", $r['detail']);
    }
}
foreach ($deferred as $d) {
    printf("[DEFERRED] %s\n      scenario: %s\n      reason:   %s\n", $d['name'], $d['scenario'], $d['reason']);
}
echo str_repeat('-', 78) . "\n";
printf("%d passed, %d failed, %d deferred\n", count($results) - $failures, $failures, count($deferred));

exit($failures > 0 ? 1 : 0);
