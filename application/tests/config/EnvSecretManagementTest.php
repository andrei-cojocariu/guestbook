<?php
/**
 * EnvSecretManagementTest — acceptance gate for tsk-004
 * ("Source database credentials, encryption key, and db_debug from the
 * environment").
 *
 * Feature contract: .ptah/audit/features/secret-management.md (three Gherkin
 * scenarios: "Credentials are sourced from the environment", "Encryption key
 * is sourced from the environment", "Database debug is off outside
 * development"). Standalone script — same convention as
 * application/tests/schema/MessagesSchemaProvisioningTest.php (tsk-001) and
 * application/tests/infra/FrozenRuntimeContainerTest.php (tsk-002): no
 * PHPUnit suite is wired yet (hooks.test in .ptah/ptah.yaml gates on
 * phpunit.xml, which lands with tsk-003), so this runs today as
 * `php application/tests/config/EnvSecretManagementTest.php` from the repo
 * root and is a drop-in TestCase body once a real suite lands.
 *
 * Each test_* method restates ONE bullet of tsk-004's Technical Acceptance
 * Criteria (.ptah/tasks/tsk-004-env-secret-management.md) 1:1 and is tied to
 * the Gherkin scenario(s) it exercises, so a reviewer can trace pass/fail
 * back to the exact criterion/scenario gated:
 *
 *   TAC 1 -> "Credentials are sourced from the environment" (no-literal Then)
 *            + "Encryption key is sourced from the environment" (no-literal Then)
 *   TAC 2 -> "Database debug is off outside development"
 *   TAC 3 -> "Credentials are sourced from the environment" (boot Then)
 *            + "Encryption key is sourced from the environment" (boot Then)
 *   TAC 4 -> standards.md "Static analysis" (no dedicated Gherkin scenario —
 *            a chore-level tooling gate, same as tsk-002/tsk-003's use of
 *            un-scenario'd TAC bullets for non-behavioral criteria)
 *
 * Live verification runs INSIDE the frozen `ci-guestbook:frozen` image (PHP
 * 5.6, tsk-002) via `docker compose build`/`docker compose run`, never
 * fabricated as a pass when Docker is unavailable — those sub-checks DEFER
 * with an explicit reason instead. The only "network" touched anywhere in
 * this file is the local Docker daemon and the compose stack's own
 * container-to-container/host-published link (127.0.0.1:8080) — no
 * third-party/external network call is made.
 *
 * Credential handling: bringing up `db` needs a MySQL root password for its
 * compose healthcheck. It is read ONLY via getenv('MYSQL_ROOT_PASSWORD')
 * (same convention as application/tests/infra/FrozenRuntimeContainerTest.php)
 * — never a literal in this file. Every other value this suite feeds the
 * `app` container (DB_HOSTNAME/DB_USERNAME/DB_PASSWORD/DB_DATABASE/
 * ENCRYPTION_KEY/CI_ENV) is a throwaway, non-secret, test-only fixture value
 * invented for this run — never a real credential, and never the pre-tsk-004
 * literal ('Start123!' / 'tVZo79a2gxgfYJsOIf5W8aBccrDHNq7m') that used to be
 * committed (TEST A asserts that literal is absent from source; it is never
 * reintroduced here).
 */

$root           = dirname(__DIR__, 3);
$dbConfigPath   = $root . '/application/config/database.php';
$appConfigPath  = $root . '/application/config/config.php';
$composePath    = $root . '/docker-compose.yml';
$ptahYamlPath   = $root . '/.ptah/ptah.yaml';
$probeRelPath   = 'application/tests/config/_env_probe.php';

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
    exec('bash ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    return [$code, implode("\n", $out)];
}

/**
 * POSIX single-quote escaping for values interpolated INTO a command string
 * that ptah_run_bash() will write into a script and hand to `bash` — never
 * PHP's escapeshellarg() for this (it targets the host shell, e.g. cmd.exe
 * on native Windows PHP, and mangles bash-only metacharacters like `!`/`%`).
 */
function ptah_posix_quote($s)
{
    return "'" . str_replace("'", "'\\''", $s) . "'";
}

function ptah_docker_available()
{
    list($code) = ptah_run_bash('docker info >/dev/null 2>&1');
    return $code === 0;
}

/** Extracts a top-level `hooks:`-section scalar value from ptah.yaml without a YAML parser. */
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
                break;
            }
            if (preg_match('/^\s{2}' . preg_quote($hookName, '/') . ':\s?(.*)$/', $line, $m)) {
                return $m[1];
            }
        }
    }
    return null;
}

function ptah_wait_db_healthy($tries = 20, $sleepUsec = 1500000)
{
    for ($i = 0; $i < $tries; $i++) {
        list(, $status) = ptah_run_bash("docker inspect --format='{{.State.Health.Status}}' guestbook-frozen-db 2>&1");
        if (trim($status) === 'healthy') {
            return true;
        }
        usleep($sleepUsec);
    }
    return false;
}

$dockerUp           = ptah_docker_available();
$mysqlRootPassword  = getenv('MYSQL_ROOT_PASSWORD');
$dbCredsAvailable   = $dockerUp && $mysqlRootPassword !== false && $mysqlRootPassword !== '';
$projectDir         = dirname($composePath);

// Former secrets purged by tsk-004 — asserted ABSENT everywhere in this
// suite, never reintroduced as a fixture value below.
$formerPassword   = 'Start123!';
$formerEncryptKey = 'tVZo79a2gxgfYJsOIf5W8aBccrDHNq7m';

// =============================================================================
// TEST A (TAC 1) — "No credential or key literal remains committed in
// database.php / config.php (standards.md 'Secret management', 'No new
// hardcoded secrets' — manual + secret-scan)."
// Scenarios: "Credentials are sourced from the environment" (no-literal Then)
//            "Encryption key is sourced from the environment" (no-literal Then)
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_no_credential_or_key_literal_remains_committed_in_database_config',
    "No credential or key literal remains committed in database.php / config.php "
    . "(standards.md \"Secret management\", \"No new hardcoded secrets\"); "
    . "covers Scenarios \"Credentials are sourced from the environment\" and "
    . "\"Encryption key is sourced from the environment\" (no-literal-committed Then)",
    function () use ($dbConfigPath, $appConfigPath, $formerPassword, $formerEncryptKey) {
        ptah_assert(is_file($dbConfigPath), "database.php not found at $dbConfigPath");
        ptah_assert(is_file($appConfigPath), "config.php not found at $appConfigPath");

        $dbSrc  = file_get_contents($dbConfigPath);
        $appSrc = file_get_contents($appConfigPath);

        // 1. The exact former committed literals must never appear again,
        // anywhere in either file (secret-scan).
        ptah_assert(strpos($dbSrc, $formerPassword) === false, "the former committed DB password literal '$formerPassword' is still present in database.php");
        ptah_assert(strpos($appSrc, $formerEncryptKey) === false, "the former committed encryption_key literal '$formerEncryptKey' is still present in config.php");

        // 2. Structural check: the 'password' array value must not be a bare
        // string literal — it must be an expression derived from a
        // getenv()-sourced variable, so a reviewer/secret-scanner can trace
        // it to the environment rather than to a hardcoded value.
        ptah_assert(
            (bool) preg_match("/'password'\s*=>\s*\(\\\$db_password\s*!==\s*FALSE\)\s*\?\s*\\\$db_password\s*:/", $dbSrc),
            "'password' in database.php is not sourced from a getenv()-derived variable — expected a ternary over \$db_password"
        );
        ptah_assert(
            (bool) preg_match("/\\\$db_password\s*=\s*getenv\('DB_PASSWORD'\)/", $dbSrc),
            "database.php does not read the DB password via getenv('DB_PASSWORD')"
        );
        // The 'password' key must never assign a plain quoted literal directly.
        ptah_assert(
            !preg_match("/'password'\s*=>\s*'[^']*'\s*,/", $dbSrc),
            "'password' in database.php is assigned a bare string literal instead of an env-derived expression"
        );

        // 3. Same structural check for username/hostname/database — each
        // must route through getenv(), even where the non-secret fallback
        // itself is a literal (e.g. 'root', 'localhost', 'guestbook' — none
        // of those is a secret).
        foreach (['DB_HOSTNAME' => 'db_hostname', 'DB_USERNAME' => 'db_username', 'DB_DATABASE' => 'db_database'] as $envVar => $phpVar) {
            ptah_assert(
                (bool) preg_match("/\\\$$phpVar\s*=\s*getenv\('" . preg_quote($envVar, '/') . "'\)/", $dbSrc),
                "database.php does not read $envVar via getenv('$envVar')"
            );
        }

        // 4. config.php's encryption_key must not be a bare string literal —
        // must be an expression derived from getenv('ENCRYPTION_KEY').
        ptah_assert(
            (bool) preg_match("/\\\$encryption_key\s*=\s*getenv\('ENCRYPTION_KEY'\)/", $appSrc),
            "config.php does not read the encryption key via getenv('ENCRYPTION_KEY')"
        );
        ptah_assert(
            !preg_match("/\\\$config\['encryption_key'\]\s*=\s*'[^']*'\s*;/", $appSrc),
            "config.php's \$config['encryption_key'] is assigned a bare string literal instead of an env-derived expression"
        );
        ptah_assert(
            (bool) preg_match("/\\\$config\['encryption_key'\]\s*=\s*\(\\\$encryption_key\s*!==\s*FALSE\)\s*\?\s*\\\$encryption_key\s*:/", $appSrc),
            "\$config['encryption_key'] in config.php is not sourced from the getenv()-derived \$encryption_key variable"
        );
    }
);

// =============================================================================
// TEST B (TAC 2) — "db_debug resolves FALSE when CI_ENV=production; no SQL
// error detail reaches the client."
// Scenario: "Database debug is off outside development"
// =============================================================================
if ($dbCredsAvailable) {
    ptah_record(
        $results,
        $failures,
        'test_db_debug_resolves_false_when_ci_env_production_no_sql_error_detail_to_client',
        'db_debug resolves FALSE when CI_ENV=production; no SQL error detail reaches the client. '
        . '(Scenario: "Database debug is off outside development")',
        function () use ($projectDir, $probeRelPath) {
            // --- Sub-check 1: db_debug truth table, live, inside the frozen
            // runtime, via the real committed database.php (not re-implemented
            // logic).
            $buildCmd = 'cd ' . ptah_posix_quote($projectDir) . ' && docker compose build app';
            list($buildCode, $buildOut) = ptah_run_bash($buildCmd);
            ptah_assert($buildCode === 0, "docker compose build app did not exit 0:\n$buildOut");

            $expectations = [
                'production'  => false,
                'testing'     => false,
                'staging'     => false, // anything other than the explicit 'development' opt-in
                'development' => true,
            ];
            foreach ($expectations as $ciEnv => $expectDebug) {
                $cmd = 'cd ' . ptah_posix_quote($projectDir)
                    . ' && docker compose run --rm -e ' . ptah_posix_quote('CI_ENV=' . $ciEnv)
                    . ' app php ' . $probeRelPath;
                list($code, $out) = ptah_run_bash($cmd);
                ptah_assert($code === 0, "probe did not exit 0 for CI_ENV=$ciEnv:\n$out");
                $json = json_decode(trim(substr($out, strpos($out, '{'))), true);
                ptah_assert(is_array($json) && array_key_exists('db_debug', $json), "probe output for CI_ENV=$ciEnv was not valid JSON with db_debug:\n$out");
                $actual = $json['db_debug'];
                ptah_assert(
                    $actual === $expectDebug,
                    "db_debug for CI_ENV=$ciEnv resolved to " . var_export($actual, true) . ', expected ' . var_export($expectDebug, true)
                );
            }
            // Also cover the unset-CI_ENV default (index.php falls back to 'development').
            list($code, $out) = ptah_run_bash(
                'cd ' . ptah_posix_quote($projectDir) . ' && docker compose run --rm app php ' . $probeRelPath
            );
            ptah_assert($code === 0, "probe did not exit 0 for unset CI_ENV:\n$out");
            $json = json_decode(trim(substr($out, strpos($out, '{'))), true);
            ptah_assert(is_array($json), "probe output for unset CI_ENV was not valid JSON:\n$out");
            ptah_assert($json['db_debug'] === true, 'db_debug for unset CI_ENV (default development) resolved to ' . var_export($json['db_debug'], true) . ', expected true');

            // --- Sub-check 2: live HTTP contrast — with a deliberately broken
            // DB connection, CI_ENV=production must not leak connection/SQL
            // detail to the client, while CI_ENV unset (development) DOES leak
            // it — proving the assertion is discriminating, not just an
            // always-empty page.
            list($upCode, $upOut) = ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose up -d db');
            ptah_assert($upCode === 0, "docker compose up -d db did not exit 0:\n$upOut");
            ptah_assert(ptah_wait_db_healthy(), 'db container did not report healthy within the poll window');

            $bogusEnvFlags = '-e CI_ENV=production -e DB_HOSTNAME=ptah-nonexistent-host -e DB_USERNAME=ptah_bogus_user -e DB_PASSWORD=ptah_bogus_pw -e DB_DATABASE=guestbook';

            $runProdCmd = 'cd ' . ptah_posix_quote($projectDir) . ' && docker compose run --rm -d ' . $bogusEnvFlags . ' app';
            list($runProdCode, $prodContainerId) = ptah_run_bash($runProdCmd);
            $prodContainerId = trim($prodContainerId);
            ptah_assert($runProdCode === 0 && $prodContainerId !== '', "starting the app container (CI_ENV=production) did not succeed:\n$prodContainerId");

            try {
                $prodBody = null;
                for ($i = 0; $i < 20; $i++) {
                    list($curlCode, $curlOut) = ptah_run_bash('curl -s -m 5 http://127.0.0.1:8080/index.php/guestbook 2>&1');
                    if ($curlCode === 0 && trim($curlOut) !== '') {
                        $prodBody = $curlOut;
                        break;
                    }
                    usleep(1000000);
                }
                ptah_assert($prodBody !== null, 'app (CI_ENV=production, broken DB creds) never returned a non-empty HTTP response within the poll window');

                foreach (['Unable to connect to your database server', 'A Database Error Occurred', 'ptah-nonexistent-host', 'ptah_bogus_user', 'ptah_bogus_pw', 'mysqli'] as $leakMarker) {
                    if (stripos($prodBody, $leakMarker) !== false) {
                        // Diagnose which mechanism leaked: the DB_driver's own
                        // display_error() (system/database/DB_driver.php:434-436)
                        // is explicitly gated `if ($this->db_debug)` -- if ITS
                        // heading is present, db_debug resolved TRUE despite
                        // CI_ENV=production, which (given TEST C's CLI probe
                        // proved the same database.php resolves db_debug=FALSE
                        // correctly for CI_ENV=production under the PHP CLI
                        // SAPI) points at index.php:56's
                        // `$_SERVER['CI_ENV']` check not being populated from
                        // the container's OS/Docker environment variable under
                        // the Apache/mod_php SAPI the way it is for CLI --
                        // unlike getenv('DB_HOSTNAME') etc. (which DID reach the
                        // driver correctly here, proving getenv() itself works
                        // fine under Apache/mod_php; only the $_SERVER-based
                        // CI_ENV lookup does not).
                        $dbDebugLeak = stripos($prodBody, 'A Database Error Occurred') !== false
                            || stripos($prodBody, 'Unable to connect to your database server') !== false;
                        $diagnosis = $dbDebugLeak
                            ? "ROOT CAUSE: db_debug's own gated display_error() rendered -- db_debug resolved TRUE "
                              . "for this live Apache/mod_php request despite CI_ENV=production being set at the "
                              . "container level. TEST C proves the same database.php resolves db_debug=FALSE "
                              . "correctly for CI_ENV=production under the PHP CLI SAPI, and getenv('DB_HOSTNAME') "
                              . "clearly reached the driver here (the bogus hostname appears below) -- so getenv() "
                              . "itself propagates fine under Apache/mod_php. Only index.php:56's "
                              . "\$_SERVER['CI_ENV'] check is not populated from the OS/Docker environment variable "
                              . "under Apache/mod_php the way it is under CLI. This is a genuine gap: the deploy "
                              . "path does not actually force db_debug=FALSE for real HTTP requests."
                            : "A PHP-level warning/error (not db_debug's own gated display_error()) leaked "
                              . "connection detail -- likely config.php's production/testing error_reporting mask, "
                              . "which excludes E_NOTICE/E_DEPRECATED/E_STRICT but NOT E_WARNING, so CI's own "
                              . "custom error handler still renders the mysqli connection warning (with a full "
                              . "backtrace) regardless of db_debug.";
                        throw new RuntimeException(
                            "CI_ENV=production response leaked SQL/connection detail to the client: found "
                            . "'$leakMarker' in body. $diagnosis"
                        );
                    }
                }
            } finally {
                ptah_run_bash("docker stop $prodContainerId >/dev/null 2>&1");
            }

            // Contrast run: same broken DB creds, CI_ENV unset (defaults to
            // 'development') — the leak SHOULD be present, proving the
            // production check above is a real discrimination and not a
            // coincidentally-blank page either way.
            $runDevCmd = 'cd ' . ptah_posix_quote($projectDir)
                . ' && docker compose run --rm -d -e DB_HOSTNAME=ptah-nonexistent-host -e DB_USERNAME=ptah_bogus_user -e DB_PASSWORD=ptah_bogus_pw -e DB_DATABASE=guestbook app';
            list($runDevCode, $devContainerId) = ptah_run_bash($runDevCmd);
            $devContainerId = trim($devContainerId);
            ptah_assert($runDevCode === 0 && $devContainerId !== '', "starting the app container (CI_ENV unset/development) did not succeed:\n$devContainerId");

            try {
                $devBody = null;
                for ($i = 0; $i < 20; $i++) {
                    list($curlCode, $curlOut) = ptah_run_bash('curl -s -m 5 http://127.0.0.1:8080/index.php/guestbook 2>&1');
                    if ($curlCode === 0 && trim($curlOut) !== '') {
                        $devBody = $curlOut;
                        break;
                    }
                    usleep(1000000);
                }
                ptah_assert($devBody !== null, 'app (CI_ENV unset, broken DB creds) never returned a non-empty HTTP response within the poll window — contrast could not be established');

                $leaked = (stripos($devBody, 'Unable to connect to your database server') !== false)
                    || (stripos($devBody, 'A Database Error Occurred') !== false)
                    || (stripos($devBody, 'ptah-nonexistent-host') !== false);
                ptah_assert($leaked, 'contrast check failed: CI_ENV unset (development) with broken DB creds did NOT leak connection detail either — the production check above is not proven discriminating');
            } finally {
                ptah_run_bash("docker stop $devContainerId >/dev/null 2>&1");
            }
        }
    );
} else {
    ptah_defer(
        $deferred,
        'test_db_debug_resolves_false_when_ci_env_production_no_sql_error_detail_to_client',
        'db_debug resolves FALSE when CI_ENV=production; no SQL error detail reaches the client.',
        $dockerUp
            ? "MYSQL_ROOT_PASSWORD not set in this process's environment — live DB/HTTP check deferred rather than falling back to a hardcoded credential"
            : 'Docker CLI/daemon not available in this environment'
    );
}

// =============================================================================
// TEST C (TAC 3) — "The env-var contract resolves inside the tsk-002 frozen
// runtime; app boots with vars set and fails closed (no silent fallback to a
// committed secret) when unset."
// Scenarios: "Credentials are sourced from the environment" (boot Then)
//            "Encryption key is sourced from the environment" (boot Then)
// =============================================================================
if ($dbCredsAvailable) {
    ptah_record(
        $results,
        $failures,
        'test_env_var_contract_resolves_in_frozen_runtime_and_fails_closed_when_unset',
        'The env-var contract resolves inside the tsk-002 frozen runtime; app boots with vars set and fails '
        . 'closed (no silent fallback to a committed secret) when unset. '
        . '(Scenarios: "Credentials are sourced from the environment", "Encryption key is sourced from the environment")',
        function () use ($projectDir, $probeRelPath, $formerPassword, $formerEncryptKey) {
            list($buildCode, $buildOut) = ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . ' && docker compose build app');
            ptah_assert($buildCode === 0, "docker compose build app did not exit 0:\n$buildOut");

            // --- Case 1: vars SET — app boots and resolves exactly the env
            // values provided, live, inside the frozen PHP 5.6 runtime.
            $setFlags = '-e CI_ENV=testing -e DB_HOSTNAME=ptah-test-host -e DB_USERNAME=ptah_test_user '
                . '-e DB_PASSWORD=ptah_test_pw -e DB_DATABASE=ptah_test_db -e ENCRYPTION_KEY=ptah-test-key-0123456789';
            $cmd = 'cd ' . ptah_posix_quote($projectDir) . " && docker compose run --rm $setFlags app php $probeRelPath";
            list($code, $out) = ptah_run_bash($cmd);
            ptah_assert($code === 0, "probe with env vars set did not exit 0:\n$out");
            $json = json_decode(trim(substr($out, strpos($out, '{'))), true);
            ptah_assert(is_array($json), "probe output (vars set) was not valid JSON:\n$out");

            ptah_assert($json['hostname'] === 'ptah-test-host', "hostname did not resolve from DB_HOSTNAME; got: {$json['hostname']}");
            ptah_assert($json['username'] === 'ptah_test_user', "username did not resolve from DB_USERNAME; got: {$json['username']}");
            ptah_assert($json['password'] === 'ptah_test_pw', "password did not resolve from DB_PASSWORD; got: {$json['password']}");
            ptah_assert($json['database'] === 'ptah_test_db', "database did not resolve from DB_DATABASE; got: {$json['database']}");
            ptah_assert($json['encryption_key'] === 'ptah-test-key-0123456789', "encryption_key did not resolve from ENCRYPTION_KEY; got: {$json['encryption_key']}");

            // --- Case 2: vars UNSET — app still boots (probe exits 0,
            // resolves *some* value), and fails closed: never the former
            // committed secret, on any code path.
            $cmd2 = 'cd ' . ptah_posix_quote($projectDir) . " && docker compose run --rm app php $probeRelPath";
            list($code2, $out2) = ptah_run_bash($cmd2);
            ptah_assert($code2 === 0, "probe with env vars unset did not exit 0 (app failed to boot):\n$out2");
            $json2 = json_decode(trim(substr($out2, strpos($out2, '{'))), true);
            ptah_assert(is_array($json2), "probe output (vars unset) was not valid JSON:\n$out2");

            ptah_assert($json2['password'] !== $formerPassword, 'password fell back to the former committed literal when DB_PASSWORD was unset — not failing closed');
            ptah_assert($json2['password'] === '', "password did not fail closed to an empty string when DB_PASSWORD was unset; got: {$json2['password']}");
            ptah_assert($json2['encryption_key'] !== $formerEncryptKey, 'encryption_key fell back to the former committed literal when ENCRYPTION_KEY was unset — not failing closed');
            ptah_assert($json2['encryption_key'] === '', "encryption_key did not fail closed to an empty string when ENCRYPTION_KEY was unset; got: {$json2['encryption_key']}");
        }
    );
} else {
    ptah_defer(
        $deferred,
        'test_env_var_contract_resolves_in_frozen_runtime_and_fails_closed_when_unset',
        'The env-var contract resolves inside the tsk-002 frozen runtime; app boots with vars set and fails '
        . 'closed (no silent fallback to a committed secret) when unset.',
        $dockerUp
            ? "MYSQL_ROOT_PASSWORD not set in this process's environment — live in-container check deferred rather than falling back to a hardcoded credential"
            : 'Docker CLI/daemon not available in this environment'
    );
}

// =============================================================================
// TEST D (TAC 4) — "hooks.analyze passes at the ramped PHPStan level on the
// touched config."
// No dedicated Gherkin scenario (chore-level tooling gate — same convention
// as tsk-002/tsk-003 TAC bullets that are not themselves rendered behavior).
// =============================================================================
ptah_record(
    $results,
    $failures,
    'test_hooks_analyze_passes_at_ramped_phpstan_level_on_touched_config',
    "hooks.analyze passes at the ramped PHPStan level on the touched config "
    . "(database.php, config.php); standards.md \"Static analysis\" is Pending "
    . "(PHPStan requires PHP>=7.1, incompatible with the frozen PHP 5.6 runtime "
    . "by construction) — this test runs the hook and asserts it exits 0, "
    . "honestly reporting the PENDING stub rather than fabricating a PHPStan pass.",
    function () use ($ptahYamlPath, $projectDir, $dockerUp) {
        $analyzeHook = ptah_read_hook($ptahYamlPath, 'analyze');
        ptah_assert($analyzeHook !== null, "could not read hooks.analyze from $ptahYamlPath");

        if (!$dockerUp) {
            throw new RuntimeException('Docker CLI/daemon not available — cannot execute hooks.analyze (which runs inside the container) to confirm even its PENDING exit-0 contract');
        }

        list($code, $out) = ptah_run_bash('cd ' . ptah_posix_quote($projectDir) . " && $analyzeHook");
        ptah_assert($code === 0, "hooks.analyze did not exit 0:\n$out");
        ptah_assert(
            stripos($out, 'PENDING') !== false,
            "hooks.analyze exited 0 without its documented PENDING stub message — if PHPStan is now actually installed/runnable, "
            . "this test needs to be upgraded to assert a real PHPStan pass on database.php/config.php instead of the stub contract:\n$out"
        );
    }
);
if ($dockerUp) {
    ptah_defer(
        $deferred,
        'test_hooks_analyze_passes_at_ramped_phpstan_level_on_touched_config (real PHPStan pass)',
        'hooks.analyze passes at the ramped PHPStan level on the touched config.',
        'PHPStan is not installed in the frozen PHP 5.6 image by construction (standards.md: Static analysis, Pending; '
        . 'PHPStan requires PHP>=7.1) — hooks.analyze verified to exit 0 with its documented PENDING stub above; '
        . 'a real PHPStan-level-4+ pass on application/config/database.php and application/config/config.php is deferred '
        . 'until a modern-PHP analysis path exists (out of tsk-004 scope, see .ptah/audit/legacy_debt.md)'
    );
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "EnvSecretManagementTest — tsk-004 gate" . ($dockerUp ? " (live Docker verification)" : " (Docker unavailable — static-only)") . "\n";
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
