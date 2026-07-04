<?php
/**
 * Test fixture (NOT a test case, NOT product code) for
 * application/tests/config/EnvSecretManagementTest.php (tsk-004).
 *
 * Includes the ACTUAL, unmodified, committed
 * application/config/database.php and application/config/config.php --
 * exactly as CodeIgniter's Config/DB loader would -- and prints the
 * resolved values as JSON on stdout. This is run INSIDE the frozen
 * `ci-guestbook:frozen` image (PHP 5.6, tsk-002) via `docker compose run`,
 * never on the host, so "the env-var contract resolves inside the tsk-002
 * frozen runtime" (tsk-004 TAC bullet 3) is verified live, not simulated.
 *
 * ENVIRONMENT is derived with the EXACT same expression index.php uses
 * (index.php:56: isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] :
 * 'development'), reproduced verbatim here so db_debug resolves precisely
 * as a real boot would for whatever CI_ENV the caller passed via
 * `docker compose run -e CI_ENV=...`.
 */
define('BASEPATH', __DIR__ . DIRECTORY_SEPARATOR);

define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');

// dirname($path, $levels) two-arg form is PHP7+ only -- this probe runs
// inside the frozen PHP 5.6 image, so climb one level at a time instead.
$root = dirname(dirname(dirname(__DIR__)));

$active_group = null;
$query_builder = null;
$db = array();
require $root . '/application/config/database.php';

$config = array();
require $root . '/application/config/config.php';

echo json_encode(array(
    'ENVIRONMENT'    => ENVIRONMENT,
    'hostname'       => $db['default']['hostname'],
    'username'       => $db['default']['username'],
    'password'       => $db['default']['password'],
    'database'       => $db['default']['database'],
    'db_debug'       => $db['default']['db_debug'],
    'encryption_key' => $config['encryption_key'],
));
