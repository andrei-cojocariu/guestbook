<?php
/**
 * FrozenRuntimeContainerTest — acceptance gate for tsk-002
 * ("Freeze the legacy runtime in a reproducible container").
 *
 * Standalone script: no PHPUnit is installed in this repo (DEBT-7 —
 * composer.json's require-dev is unresolvable: dead Composer-1 Packagist
 * protocol + invalid `mikey179/vfsStream` casing — see
 * .ptah/audit/legacy_debt.md). It runs today as
 * `php application/tests/infra/FrozenRuntimeContainerTest.php` from the repo
 * root, and is a drop-in TestCase body once a real suite lands. It follows
 * the same convention `application/tests/schema/MessagesSchemaProvisioningTest.php`
 * (tsk-001) established.
 *
 * Each test_* method name and its $scenario string restate ONE of tsk-002's
 * five Technical Acceptance Criteria bullets 1:1, so a reviewer can trace
 * pass/fail back to the exact criterion gated. tsk-002 is a `chore` task with
 * no `.ptah/audit/features/<slug>.md` Gherkin contract of its own (chores
 * freeze environment, they do not add product behavior) — the task's TAC is
 * therefore the executable contract here, per the same convention already
 * used for tsk-001's static gate.
 *
 * Where the host has a working Docker Engine, each test does LIVE
 * verification against `ci-guestbook:frozen` / `docker-compose.yml`, not just
 * text inspection. No product-code network call is exercised anywhere in
 * this file: the only "network" touched is the local Docker daemon and
 * images already resolved in the local image cache (this suite does not
 * pull), and the only ports involved are the compose stack's own
 * container-to-container link. Building the `app` image runs `composer
 * install` (baked into the Dockerfile, not initiated fresh here); that step
 * already fails deterministically BEFORE any network call per DEBT-7 and is
 * treated as PENDING by the image build itself, not by this test.
 *
 * If the Docker CLI/daemon is unavailable in the environment this runs in,
 * the docker-dependent tests are reported DEFERRED (never fabricated as a
 * pass) with the reason logged; the tag-pinning, diff-scope, and
 * git-tracking checks below are static and always run.
 *
 * Credential handling: the live DB checks need the MySQL root password to
 * shell out `docker exec ... mysql -uroot -p...`. It is read ONLY via
 * getenv('MYSQL_ROOT_PASSWORD') (the same var docker-compose.yml sources
 * from the git-ignored `.env` at the repo root) — never a literal in this
 * file. If the env var is unset, those specific checks DEFER with an
 * explicit reason rather than falling back to any hardcoded value.
 */

$root           = dirname(__DIR__, 3);
$dockerfilePath = $root . '/Dockerfile';
$composePath    = $root . '/docker-compose.yml';
$ptahYamlPath   = $root . '/.ptah/ptah.yaml';
$schemaPath     = $root . '/schema/messages.sql';

/** @var array<int, array{name:string,scenario:string,status:string,detail:string}> $results */
$results  = [];
$deferred = [];
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

function ptah_defer(array &$deferred, $name, $scenario, $reason)
{
    $deferred[] = ['name' => $name, 'scenario' => $scenario, 'reason' => $reason];
}

/**
 * Runs a shell command through bash (so the POSIX `docker compose run ... sh -c '...'`
 * hook strings from .ptah/ptah.yaml execute with their intended semantics on
 * every host OS, Windows included) and returns [exitCode, outputString].
 */
function ptah_run_bash($cmd)
{
    $tmp = tempnam(sys_get_temp_dir(), 'ptah_hook_');
    file_put_contents($tmp, "#!/bin/sh\n" . $cmd . "\n");
    $out  = [];
    $code = 0;
    // NOTE: escapeshellarg() here quotes $tmp for the OUTER exec() call,
    // which PHP dispatches through THIS process's own host shell (cmd.exe on
    // Windows, /bin/sh elsewhere) — that usage is correct on every host.
    exec('bash ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    return [$code, implode("\n", $out)];
}

/**
 * POSIX single-quote escaping for values interpolated INTO a command string
 * that ptah_run_bash() will write into a script and hand to `bash` — never
 * use PHP's escapeshellarg() for this. escapeshellarg() targets the CURRENT
 * process's host shell (cmd.exe on native Windows PHP), which mangles
 * bash-only metacharacters: on Windows it silently replaces `!` and `%` with
 * a space instead of escaping them, silently corrupting values like a MySQL
 * password containing `!` — the exact bug this helper fixes. The target
 * interpreter for these values is always bash (POSIX), regardless of host
 * OS, so they must always be POSIX-quoted.
 */
function ptah_posix_quote($s)
{
    return "'" . str_replace("'", "'\\''", $s) . "'";
}

function ptah_docker_available()
{
    // exec()'s NUL redirection is unreliable cross-shell; use bash directly.
    list($code2) = ptah_run_bash('docker info >/dev/null 2>&1');
    return $code2 === 0;
}

/** Extracts a top-level `hooks:`-section scalar value (e.g. "build", "test") from ptah.yaml without a YAML parser. */
function ptah_read_hook($ptahYamlPath, $hookName)
{
    $lines = file($ptahYamlPath, FILE_IGNORE_NEW_LINES);
    ptah_assert($lines !== false, "could not read $ptahYamlPath");
    $inHooks = false;
    foreach ($lines as $line) {
        if (preg_match('/^hooks:\s*$/', $line)) {
            $inHooks = true;
            continue;
        }
        if ($inHooks) {
            if (preg_match('/^\S/', $line)) {
                break; // dedent out of the hooks: block
            }
            if (preg_match('/^\s{2}' . preg_quote($hookName, '/') . ':\s?(.*)$/', $line, $m)) {
                return $m[1];
            }
        }
    }
    return null;
}

/** Reads a YAML-frontmatter scalar (e.g. "status") out of a .ptah/tasks/*.md file without a YAML parser. */
function ptah_read_frontmatter_field($path, $field)
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return null;
    }
    $inFrontmatter = false;
    foreach ($lines as $line) {
        if (trim($line) === '---') {
            if (!$inFrontmatter) {
                $inFrontmatter = true;
                continue;
            }
            break; // closing --- of the frontmatter block
        }
        if ($inFrontmatter && preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)$/', $line, $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

$dockerUp = ptah_docker_available();

// The live DB-boot checks below shell out `docker exec ... mysql -uroot -p...`
// and must never embed the credential as a literal (secrets-protocol
// instant-rejection trigger — see Audit Feedback, rejection 1). The value is
// read once, here, via getenv('MYSQL_ROOT_PASSWORD') — the same variable
// docker-compose.yml sources from the git-ignored `.env` at the repo root.
// If it is not set in this process's environment, the live DB checks DEFER
// (same honest-DEFER convention already used for "Docker unavailable")
// rather than falling back to any hardcoded value.
$mysqlRootPassword = getenv('MYSQL_ROOT_PASSWORD');
$dbCredsAvailable  = $dockerUp && $mysqlRootPassword !== false && $mysqlRootPassword !== '';

// =============================================================================
// TAC 1: "All runtime/service versions are pinned (no floating tags); the
//         stack boots clean."
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_all_runtime_service_versions_are_pinned_no_floating_tags_stack_boots_clean',
    'All runtime/service versions are pinned (no floating tags); the stack boots clean.',
    function () use ($dockerfilePath, $composePath, $dockerUp) {
        ptah_assert(is_file($dockerfilePath), "Dockerfile not found at $dockerfilePath");
        $dockerfile = file_get_contents($dockerfilePath);

        preg_match_all('/^\s*FROM\s+(\S+)/mi', $dockerfile, $fromMatches);
        ptah_assert(count($fromMatches[1]) > 0, 'Dockerfile declares no FROM statement');
        foreach ($fromMatches[1] as $image) {
            ptah_assert(
                !preg_match('/:latest\b/i', $image) && strpos($image, ':') !== false,
                "Dockerfile FROM `$image` is not pinned to an explicit non-latest tag"
            );
        }

        preg_match_all('/^\s*COPY\s+--from=(\S+)/mi', $dockerfile, $copyFromMatches);
        foreach ($copyFromMatches[1] as $image) {
            ptah_assert(
                !preg_match('/:latest\b/i', $image) && strpos($image, ':') !== false,
                "Dockerfile COPY --from=$image is not pinned to an explicit non-latest tag"
            );
        }

        ptah_assert(is_file($composePath), "docker-compose.yml not found at $composePath");
        $compose = file_get_contents($composePath);
        preg_match_all('/^\s*image:\s*(\S+)\s*$/mi', $compose, $imageMatches);
        ptah_assert(count($imageMatches[1]) > 0, 'docker-compose.yml declares no image: values');
        foreach ($imageMatches[1] as $image) {
            ptah_assert(
                !preg_match('/:latest\b/i', $image),
                "docker-compose.yml image `$image` floats on the `latest` tag"
            );
        }

        if ($dockerUp) {
            $projectDir = dirname($composePath);

            // Live: the image actually builds end-to-end from this Dockerfile.
            list($code, $out) = ptah_run_bash(
                'cd ' . ptah_posix_quote($projectDir) . ' && docker compose build app'
            );
            ptah_assert($code === 0, "docker compose build app did not exit 0:\n$out");

            // Live: "the stack boots clean" — bring both services up and
            // require the compose healthcheck to actually report healthy,
            // not just that `build` succeeded.
            try {
                list($upCode, $upOut) = ptah_run_bash(
                    'cd ' . ptah_posix_quote($projectDir) . ' && docker compose up -d'
                );
                ptah_assert($upCode === 0, "docker compose up -d did not exit 0:\n$upOut");

                $healthy = false;
                for ($i = 0; $i < 20; $i++) {
                    list(, $status) = ptah_run_bash("docker inspect --format='{{.State.Health.Status}}' guestbook-frozen-db 2>&1");
                    if (trim($status) === 'healthy') {
                        $healthy = true;
                        break;
                    }
                    usleep(1500000);
                }
                ptah_assert($healthy, 'stack did not boot clean — db container did not report healthy within the poll window');

                list($psCode, $psOut) = ptah_run_bash(
                    'cd ' . ptah_posix_quote($projectDir) . ' && docker compose ps --format json'
                );
                ptah_assert($psCode === 0, "docker compose ps did not exit 0:\n$psOut");
            } finally {
                ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose down -v');
            }
        }
    }
);

if (!$dockerUp) {
    ptah_defer(
        $deferred,
        'test_all_runtime_service_versions_are_pinned_no_floating_tags_stack_boots_clean (live boot)',
        'All runtime/service versions are pinned (no floating tags); the stack boots clean.',
        'Docker CLI/daemon not available in this environment — static tag-pinning check ran, live boot deferred'
    );
}

// =============================================================================
// TAC 2: "schema/messages.sql applies idempotently on boot; re-apply against
//         a populated table and rollback are verified against the live DB
//         (satisfies tsk-001's deferral)."
// =============================================================================
if ($dbCredsAvailable) {
    ptah_record(
        $results,
        $failures,
        'test_schema_applies_idempotently_reapply_against_populated_table_and_rollback_verified_live',
        "schema/messages.sql applies idempotently on boot; re-apply against a populated table and rollback are verified against the live DB (satisfies tsk-001's deferral).",
        function () use ($composePath, $mysqlRootPassword, $schemaPath) {
            $projectDir = dirname($composePath);
            list($code, $out) = ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose up -d db');
            ptah_assert($code === 0, "docker compose up -d db did not exit 0:\n$out");

            try {
                $healthy = false;
                for ($i = 0; $i < 20; $i++) {
                    list(, $status) = ptah_run_bash("docker inspect --format='{{.State.Health.Status}}' guestbook-frozen-db 2>&1");
                    if (trim($status) === 'healthy') {
                        $healthy = true;
                        break;
                    }
                    usleep(1500000);
                }
                ptah_assert($healthy, 'db container did not report healthy within the poll window');

                // Password is read from getenv('MYSQL_ROOT_PASSWORD') (never a
                // literal — secrets-protocol instant-rejection trigger) and
                // shell-escaped before interpolation. mysql client emits a
                // benign "Using a password on the command line interface can
                // be insecure" warning on stderr; discard it at the source
                // (not merged into our captured stdout) so it cannot corrupt
                // the trimmed value comparisons below.
                $pw     = ptah_posix_quote($mysqlRootPassword);
                $mysqlDb = "docker exec guestbook-frozen-db mysql -uroot -p$pw guestbook -N";

                // 1. Forward apply on first boot: messages table present,
                //    empty, correct shape.
                list($tCode, $tables) = ptah_run_bash("$mysqlDb -e \"SHOW TABLES LIKE 'messages';\" 2>/dev/null");
                ptah_assert($tCode === 0 && trim($tables) === 'messages', "messages table not present on first boot; got: $tables");

                list($cCode, $countOut) = ptah_run_bash("$mysqlDb -e 'SELECT COUNT(*) FROM messages;' 2>/dev/null");
                ptah_assert($cCode === 0 && trim($countOut) === '0', 'messages table is not empty on first boot; row count = ' . trim($countOut));

                list($dCode, $describe) = ptah_run_bash("$mysqlDb -e 'DESCRIBE messages;' 2>/dev/null");
                ptah_assert($dCode === 0, "could not DESCRIBE messages table:\n$describe");
                foreach (['name', 'email', 'message', 'received_on'] as $col) {
                    ptah_assert(strpos($describe, $col) !== false, "expected column `$col` missing from the booted messages table: $describe");
                }

                // 2. Live insert with the model's exact insert shape (name,
                //    email, message) — received_on populated entirely by the
                //    DB-side default, no application code involved.
                list($iCode, $iOut) = ptah_run_bash(
                    "$mysqlDb -e \"INSERT INTO messages (name, email, message) VALUES ('gate-test','gate@example.test','characterization insert');\" 2>&1"
                );
                ptah_assert($iCode === 0, "live insert with the model's exact shape failed:\n$iOut");

                list(, $countAfterInsert) = ptah_run_bash("$mysqlDb -e 'SELECT COUNT(*) FROM messages;' 2>/dev/null");
                ptah_assert(trim($countAfterInsert) === '1', 'row count after the live insert is not 1: ' . trim($countAfterInsert));

                list(, $receivedOn) = ptah_run_bash("$mysqlDb -e \"SELECT received_on FROM messages WHERE name='gate-test';\" 2>/dev/null");
                ptah_assert(trim($receivedOn) !== '' && strtoupper(trim($receivedOn)) !== 'NULL', 'received_on was not populated by the DB-side default on insert');

                // 3. Idempotent re-apply of schema/messages.sql against the
                //    now-populated table — must be a safe no-op.
                $schemaSql = file_get_contents($schemaPath);
                ptah_assert($schemaSql !== false, "could not read $schemaPath");
                $reapplyScript = "docker exec -i guestbook-frozen-db mysql -uroot -p$pw guestbook <<'PTAH_SCHEMA_SQL'\n" . $schemaSql . "\nPTAH_SCHEMA_SQL\n";
                list($rCode, $rOut) = ptah_run_bash($reapplyScript);
                ptah_assert($rCode === 0, "re-applying schema/messages.sql against the populated table did not exit 0:\n$rOut");

                list(, $countAfterReapply) = ptah_run_bash("$mysqlDb -e 'SELECT COUNT(*) FROM messages;' 2>/dev/null");
                ptah_assert(trim($countAfterReapply) === '1', 're-apply against a populated table changed the row count (not a safe no-op): ' . trim($countAfterReapply));

                // 4. Rollback (DROP TABLE IF EXISTS messages) — and its own
                //    idempotency (safe to run twice).
                list($d1Code, $d1Out) = ptah_run_bash("$mysqlDb -e 'DROP TABLE IF EXISTS messages;' 2>&1");
                ptah_assert($d1Code === 0, "rollback (DROP TABLE IF EXISTS messages) did not exit 0:\n$d1Out");

                list(, $tablesAfterDrop) = ptah_run_bash("$mysqlDb -e \"SHOW TABLES LIKE 'messages';\" 2>/dev/null");
                ptah_assert(trim($tablesAfterDrop) === '', 'messages table still present after rollback');

                list($d2Code, $d2Out) = ptah_run_bash("$mysqlDb -e 'DROP TABLE IF EXISTS messages;' 2>&1");
                ptah_assert($d2Code === 0, "re-running rollback a second time (idempotency) did not exit 0:\n$d2Out");

                // 5. Restart-proof: re-applying schema/messages.sql after the
                //    rollback recreates the table, empty, same shape.
                list($r2Code, $r2Out) = ptah_run_bash($reapplyScript);
                ptah_assert($r2Code === 0, "re-applying schema/messages.sql after rollback did not exit 0:\n$r2Out");

                list(, $countAfterRestart) = ptah_run_bash("$mysqlDb -e 'SELECT COUNT(*) FROM messages;' 2>/dev/null");
                ptah_assert(trim($countAfterRestart) === '0', 'messages table is not empty after the restart-proof re-apply: ' . trim($countAfterRestart));

                list($d3Code, $describeAfterRestart) = ptah_run_bash("$mysqlDb -e 'DESCRIBE messages;' 2>/dev/null");
                ptah_assert($d3Code === 0, "could not DESCRIBE messages table after restart-proof re-apply:\n$describeAfterRestart");
                foreach (['name', 'email', 'message', 'received_on'] as $col) {
                    ptah_assert(strpos($describeAfterRestart, $col) !== false, "expected column `$col` missing after restart-proof re-apply: $describeAfterRestart");
                }
            } finally {
                ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose down -v');
            }
        }
    );
} elseif (!$dockerUp) {
    ptah_defer(
        $deferred,
        'test_schema_applies_idempotently_reapply_against_populated_table_and_rollback_verified_live',
        "schema/messages.sql applies idempotently on boot; re-apply against a populated table and rollback are verified against the live DB (satisfies tsk-001's deferral).",
        'Docker CLI/daemon not available in this environment'
    );
} else {
    ptah_defer(
        $deferred,
        'test_schema_applies_idempotently_reapply_against_populated_table_and_rollback_verified_live',
        "schema/messages.sql applies idempotently on boot; re-apply against a populated table and rollback are verified against the live DB (satisfies tsk-001's deferral).",
        "MYSQL_ROOT_PASSWORD not set in this process's environment — live DB check deferred rather than falling back to a hardcoded credential"
    );
}

// =============================================================================
// TAC 3: "PHPUnit is runnable inside the container and composer.lock is
//         committed (standards.md 'Install & pin toolchain')."
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_phpunit_runnable_inside_container_and_composer_lock_committed',
    "PHPUnit is runnable inside the container and composer.lock is committed (standards.md \"Install & pin toolchain\").",
    function () use ($root, $composePath, $dockerUp) {
        $problems = [];

        // Sub-check A: composer.lock is committed (tracked by git). Static,
        // always runs regardless of Docker availability.
        $out  = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch composer.lock 2>&1', $out, $code);
        if ($code !== 0) {
            $problems[] = 'composer.lock is not committed/tracked by git — blocked by DEBT-7 '
                . '(composer.json require-dev has an invalid `mikey179/vfsStream` package casing that '
                . 'Composer 2.x hard-errors on before any network call, and the dead Composer-1 Packagist '
                . 'metadata protocol means Composer 1.x cannot resolve it either) and DEBT-9 '
                . '(repo-root .gitignore still ignores composer.lock even if it were generated). '
                . 'Fixing composer.json is outside this task\'s environment-only scope — flagged for a '
                . 'dedicated dependency-manifest task, not fabricated here.';
        }

        // Sub-check B: vendor/bin/phpunit is executable inside the built
        // image. Live, only when Docker is available.
        if ($dockerUp) {
            $projectDir = dirname($composePath);
            list($buildCode, $buildOut) = ptah_run_bash(
                'cd ' . ptah_posix_quote($projectDir) . ' && docker compose build app'
            );
            if ($buildCode !== 0) {
                $problems[] = "docker compose build app did not exit 0:\n$buildOut";
            } else {
                list($phpunitCode, $phpunitOut) = ptah_run_bash(
                    'cd ' . ptah_posix_quote($projectDir)
                    . " && docker compose run --rm app sh -c '[ -x vendor/bin/phpunit ] && vendor/bin/phpunit --version' 2>&1"
                );
                ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose down -v');
                if ($phpunitCode !== 0) {
                    $problems[] = 'vendor/bin/phpunit is not runnable inside the container — PHPUnit is not '
                        . 'installed (blocked by the same DEBT-7 composer manifest defect as composer.lock); '
                        . "command output:\n$phpunitOut";
                }
            }
        } else {
            $problems[] = 'Docker CLI/daemon not available in this environment — the in-container PHPUnit '
                . 'check could not run live (composer.lock static check above still applies)';
        }

        if (count($problems) > 0) {
            throw new RuntimeException(implode("\n---\n", $problems));
        }
    }
);

// =============================================================================
// TAC 4: "No product code or committed app config (database.php, config.php)
//         is mutated; this task adds environment only."
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_no_product_code_or_committed_app_config_mutated_environment_only',
    'No product code or committed app config (database.php, config.php) is mutated; this task adds environment only.',
    function () use ($root) {
        $out  = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($root) . ' merge-base HEAD master 2>&1', $out, $code);
        ptah_assert($code === 0 && count($out) === 1, 'could not resolve merge-base HEAD..master: ' . implode("\n", $out));
        $base = trim($out[0]);

        $out2  = [];
        $code2 = 0;
        exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($base) . ' HEAD 2>&1', $out2, $code2);
        ptah_assert($code2 === 0, 'git diff --name-only failed: ' . implode("\n", $out2));
        ptah_assert(count($out2) > 0, 'tsk-002 branch has no diff from master — expected container/build files to have been added');

        // Explicit, named check on the two files the TAC calls out by name.
        foreach (['application/config/database.php', 'application/config/config.php'] as $configFile) {
            ptah_assert(
                !in_array($configFile, $out2, true),
                "committed app config `$configFile` was mutated by this branch — tsk-002 must add environment only"
            );
        }

        $allowed = [
            '#^Dockerfile$#',
            '#^docker-compose\.yml$#',
            '#^\.dockerignore$#',
            '#^\.gitignore$#',
            '#^\.env(\.|$)#',
            '#^\.ptah/#',
            '#^schema/#',
            // Test/infra-only, not product code — this acceptance gate itself
            // lives here.
            '#^application/tests/#',
            '#^README\.md$#',
        ];
        // Blanket 'application/' is deliberately NOT here — it would
        // re-disallow application/tests/ above via this second net. Every
        // real product-code subdirectory is named explicitly instead.
        $disallowedPrefixes = [
            'application/controllers/', 'application/models/', 'application/views/',
            'application/config/', 'application/core/', 'application/helpers/',
            'application/hooks/', 'application/libraries/', 'application/language/',
            'application/third_party/', 'application/cache/', 'application/logs/',
            'index.php', 'css/', 'js/', 'img/', 'font/', 'sass/', 'system/', 'user_guide/',
        ];

        foreach ($out2 as $file) {
            $isAllowed = false;
            foreach ($allowed as $pattern) {
                if (preg_match($pattern, $file)) {
                    $isAllowed = true;
                    break;
                }
            }
            ptah_assert($isAllowed, "changed file `$file` is not a recognized container/build/KB file — possible product-code drift");

            foreach ($disallowedPrefixes as $prefix) {
                ptah_assert(
                    strpos($file, $prefix) !== 0,
                    "changed file `$file` touches product/vendor code (`$prefix*`) — tsk-002 must be container/build-only"
                );
            }
        }
    }
);

// =============================================================================
// TAC 5: "Live-DB verification for behavioral suites is [deferred: tsk-003]
//         — the net is authored against this frozen runtime."
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_live_db_verification_for_behavioral_suites_is_deferred_to_tsk003',
    'Live-DB verification for behavioral suites is [deferred: tsk-003] — the net is authored against this frozen runtime.',
    function () use ($root) {
        $tsk003Glob = glob($root . '/.ptah/tasks/tsk-003*.md');
        ptah_assert(count($tsk003Glob) === 1, 'expected exactly one .ptah/tasks/tsk-003*.md task file, found ' . count($tsk003Glob));
        $status = ptah_read_frontmatter_field($tsk003Glob[0], 'status');
        ptah_assert($status !== null, 'could not read status: from ' . $tsk003Glob[0] . ' frontmatter');
        ptah_assert(
            $status !== 'done',
            "tsk-003 (the behavioral characterization net) is already 'done' — the deferral this TAC bullet asserts no longer holds; "
            . 'this frozen-runtime task must not claim to also deliver the behavioral net'
        );

        // This task must not itself smuggle in a behavioral, live-DB test
        // suite — only infra/schema-provisioning gates exist at this stage;
        // the behavioral net is tsk-003's deliverable, authored against this
        // frozen runtime, not this task's.
        $testFiles = glob($root . '/application/tests/*/*.php');
        $ownedDirs = ['infra', 'schema'];
        foreach ($testFiles as $testFile) {
            $relative = str_replace('\\', '/', substr($testFile, strlen($root . '/application/tests/')));
            $dir      = explode('/', $relative)[0];
            ptah_assert(
                in_array($dir, $ownedDirs, true),
                "found test file `$relative` outside the infra/schema gates tsk-001/tsk-002 own — behavioral suites "
                . 'are deferred to tsk-003 and must not be introduced here'
            );
        }
    }
);

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "FrozenRuntimeContainerTest — tsk-002 gate" . ($dockerUp ? " (live Docker verification)" : " (Docker unavailable — static-only)") . "\n";
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
