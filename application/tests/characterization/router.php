<?php
/**
 * Router script for PHP's built-in web server (`php -S host:port -t <root>
 * router.php`), used ONLY by the tsk-003 characterization net
 * (`SignAndListFlowTest`) to black-box exercise the project's REAL front
 * controller (`index.php`) -- no mocked framework internals, no test double
 * standing in for CodeIgniter.
 *
 * Per DEBT-8 (`.ptah/audit/legacy_debt.md`), characterization tooling that
 * runs under the frozen PHP 5.6 CLI must black-box the app rather than
 * `require`ing CI's bootstrap in-process (CI defines constants/classes once
 * per process and cannot be safely re-entered per test case). Spawning a
 * real `php -S` server and issuing real HTTP requests against it sidesteps
 * that entirely, at the cost of needing this thin router so PATH routing
 * behaves the way Apache + mod_rewrite would (see
 * `application/config/config.php`'s `uri_protocol = 'REQUEST_URI'`).
 *
 * This file adds NO behavior of its own beyond that routing shim; it never
 * touches `$_POST`, session, or any product code.
 */

$projectRoot = realpath(__DIR__ . '/../../..');
if ($projectRoot === false) {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    echo 'characterization router: cannot resolve project root';
    exit(1);
}

chdir($projectRoot);

// index.php resolves its own paths relative to SCRIPT_NAME/REQUEST_URI, the
// same way it would sitting behind Apache at the site root -- force
// SCRIPT_NAME to '/index.php' so CI's URI class (uri_protocol =
// REQUEST_URI, application/config/config.php) strips exactly that prefix,
// regardless of the literal request path the test sent.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $projectRoot . '/index.php';

require $projectRoot . '/index.php';
