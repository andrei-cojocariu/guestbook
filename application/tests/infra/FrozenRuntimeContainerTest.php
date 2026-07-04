<?php
/**
 * FrozenRuntimeContainerTest — acceptance gate for tsk-002
 * ("Freeze the legacy runtime in a reproducible container").
 *
 * Standalone script: no PHPUnit is installed in this repo yet (DEBT-7 —
 * composer.json's require-dev is unresolvable: dead Composer-1 Packagist
 * protocol + invalid `mikey179/vfsStream` casing — see
 * .ptah/audit/legacy_debt.md). It runs today as
 * `php application/tests/infra/FrozenRuntimeContainerTest.php` from the repo
 * root, and is a drop-in TestCase body once a real suite lands. It follows
 * the same convention `application/tests/schema/MessagesSchemaProvisioningTest.php`
 * (tsk-001) established.
 *
 * Unlike tsk-001's test (which was static-only because no container existed
 * yet), this gate is for the task that DELIVERS the container — so where the
 * host has a working Docker Engine, each test does LIVE verification against
 * `ci-guestbook:frozen` / `docker-compose.yml`, not just text inspection. No
 * product-code network call is exercised anywhere in this file: the only
 * "network" touched is the local Docker daemon and images already resolved
 * in the local image cache (this suite does not pull), and the only ports
 * involved are the compose stack's own container-to-container link.
 *
 * If the Docker CLI/daemon is unavailable in the environment this runs in,
 * the docker-dependent tests are reported DEFERRED (never fabricated as a
 * pass) with the reason logged; the tag-pinning and diff-scope checks below
 * are static and always run.
 *
 * Each test_* method name and its $scenario string restate one bullet of the
 * task's Technical Acceptance Criteria 1:1, so a reviewer can trace pass/fail
 * back to the exact criterion gated.
 */

$root           = dirname(__DIR__, 3);
$dockerfilePath = $root . '/Dockerfile';
$composePath    = $root . '/docker-compose.yml';
$ptahYamlPath   = $root . '/.ptah/ptah.yaml';

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

function ptah_docker_available()
{
    exec('docker info >' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null') . ' 2>&1', $out, $code);
    // exec()'s NUL redirection is unreliable cross-shell; fall back to bash.
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

$dockerUp = ptah_docker_available();

// ---------------------------------------------------------------------------
// TAC: "Image builds reproducibly; all tags pinned, no `latest`."
// ---------------------------------------------------------------------------
ptah_record(
    $results,
    $failures,
    'test_image_builds_reproducibly_all_tags_pinned_no_latest',
    'Image builds reproducibly; all tags pinned, no `latest`.',
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
            // Live: the image actually builds end-to-end from this Dockerfile.
            list($code, $out) = ptah_run_bash(
                'cd ' . escapeshellarg(dirname($composePath)) . ' && docker compose build app'
            );
            ptah_assert($code === 0, "docker compose build app did not exit 0:\n$out");
        }
    }
);

if (!$dockerUp) {
    ptah_defer(
        $deferred,
        'test_image_builds_reproducibly_all_tags_pinned_no_latest (live build)',
        'Image builds reproducibly; all tags pinned, no `latest`.',
        'Docker CLI/daemon not available in this environment — static tag-pinning check ran, live `docker compose build` deferred'
    );
}

// ---------------------------------------------------------------------------
// TAC: "Container boots with the tsk-001 schema applied and an empty
//       `messages` table."
// ---------------------------------------------------------------------------
if ($dockerUp) {
    ptah_record(
        $results,
        $failures,
        'test_container_boots_with_tsk001_schema_applied_and_empty_messages_table',
        'Container boots with the tsk-001 schema applied and an empty `messages` table.',
        function () use ($composePath) {
            $projectDir = dirname($composePath);
            list($code, $out) = ptah_run_bash('cd ' . escapeshellarg($projectDir) . ' && docker compose up -d db');
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

                // mysql client emits a benign "Using a password on the command
                // line interface can be insecure" warning on stderr; discard it
                // at the source (not merged into our captured stdout) so it
                // cannot corrupt the trimmed value comparisons below.
                list($tCode, $tables) = ptah_run_bash(
                    "docker exec guestbook-frozen-db mysql -uroot -pStart123! -N -e \"SHOW TABLES FROM guestbook LIKE 'messages';\" 2>/dev/null"
                );
                ptah_assert($tCode === 0 && trim($tables) === 'messages', "messages table not present after container boot; got: $tables");

                list($cCode, $countOut) = ptah_run_bash(
                    "docker exec guestbook-frozen-db mysql -uroot -pStart123! -N -e 'SELECT COUNT(*) FROM guestbook.messages;' 2>/dev/null"
                );
                ptah_assert($cCode === 0, "could not query messages row count:\n$countOut");
                ptah_assert(trim($countOut) === '0', "messages table is not empty on boot; row count = " . trim($countOut));

                list($dCode, $describe) = ptah_run_bash(
                    "docker exec guestbook-frozen-db mysql -uroot -pStart123! -N -e 'DESCRIBE guestbook.messages;' 2>/dev/null"
                );
                ptah_assert($dCode === 0, "could not DESCRIBE messages table:\n$describe");
                foreach (['name', 'email', 'message', 'received_on'] as $col) {
                    ptah_assert(strpos($describe, $col) !== false, "expected column `$col` missing from the booted messages table: $describe");
                }
            } finally {
                ptah_run_bash('cd ' . escapeshellarg($projectDir) . ' && docker compose down -v');
            }
        }
    );
} else {
    ptah_defer(
        $deferred,
        'test_container_boots_with_tsk001_schema_applied_and_empty_messages_table',
        'Container boots with the tsk-001 schema applied and an empty `messages` table.',
        'Docker CLI/daemon not available in this environment'
    );
}

// ---------------------------------------------------------------------------
// TAC: "`hooks.build` and `hooks.test` exit 0 inside the container."
// ---------------------------------------------------------------------------
if ($dockerUp) {
    ptah_record(
        $results,
        $failures,
        'test_hooks_build_and_test_exit_zero_inside_the_container',
        '`hooks.build` and `hooks.test` exit 0 inside the container.',
        function () use ($ptahYamlPath, $root) {
            $buildHook = ptah_read_hook($ptahYamlPath, 'build');
            $testHook  = ptah_read_hook($ptahYamlPath, 'test');
            ptah_assert($buildHook !== null, 'could not find hooks.build in .ptah/ptah.yaml');
            ptah_assert($testHook !== null, 'could not find hooks.test in .ptah/ptah.yaml');
            ptah_assert(strpos($buildHook, 'docker compose') !== false, 'hooks.build no longer runs inside the container (docker compose) per tsk-002 TAC');
            ptah_assert(strpos($testHook, 'docker compose') !== false, 'hooks.test no longer runs inside the container (docker compose) per tsk-002 TAC');

            list($buildCode, $buildOut) = ptah_run_bash('cd ' . escapeshellarg($root) . ' && ' . $buildHook);
            ptah_assert($buildCode === 0, "hooks.build did not exit 0:\n$buildOut");

            list($testCode, $testOut) = ptah_run_bash('cd ' . escapeshellarg($root) . ' && ' . $testHook);
            ptah_assert($testCode === 0, "hooks.test did not exit 0:\n$testOut");
        }
    );

    // Final teardown of anything hooks.build/hooks.test left running.
    ptah_run_bash('cd ' . escapeshellarg(dirname($composePath)) . ' && docker compose down -v');
} else {
    ptah_defer(
        $deferred,
        'test_hooks_build_and_test_exit_zero_inside_the_container',
        '`hooks.build` and `hooks.test` exit 0 inside the container.',
        'Docker CLI/daemon not available in this environment'
    );
}

// ---------------------------------------------------------------------------
// TAC: "No product-code changes; this task touches only container/build
//       files."
// ---------------------------------------------------------------------------
ptah_record(
    $results,
    $failures,
    'test_no_product_code_changes_only_container_build_files_touched',
    'No product-code changes; this task touches only container/build files.',
    function () use ($root) {
        $out  = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($root) . ' merge-base HEAD master 2>&1', $out, $code);
        ptah_assert($code === 0 && count($out) === 1, 'could not resolve merge-base HEAD..master: ' . implode("\n", $out));
        $base = trim($out[0]);

        $out2 = [];
        exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($base) . ' HEAD 2>&1', $out2, $code2);
        ptah_assert($code2 === 0, 'git diff --name-only failed: ' . implode("\n", $out2));
        ptah_assert(count($out2) > 0, 'tsk-002 branch has no diff from master — expected container/build files to have been added');

        $allowed = [
            '#^Dockerfile$#',
            '#^docker-compose\.yml$#',
            '#^\.dockerignore$#',
            '#^\.ptah/#',
            '#^schema/#',
        ];
        $disallowedPrefixes = ['application/', 'index.php', 'css/', 'js/', 'img/', 'font/', 'sass/', 'system/', 'user_guide/'];

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
