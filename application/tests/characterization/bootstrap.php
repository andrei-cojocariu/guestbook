<?php
/**
 * PHPUnit bootstrap for the tsk-003 characterization net.
 *
 * Runs INSIDE the frozen `ci-guestbook:frozen` image, under its own PHP 5.6
 * CLI (`hooks.test` -> `php vendor/bin/phpunit`, gated on `phpunit.xml`
 * existing per `.ptah/ptah.yaml`) -- see DEBT-8 (`.ptah/audit/legacy_debt.md`)
 * for why every file under this directory must stay PHP-5.6-syntax-safe
 * (no `??`, no scalar/return type hints, no 2-arg `dirname()`, etc.).
 *
 * Defines PTAH_CHARACTERIZATION_ROOT once, resolved relative to this file, so
 * every test/support file in this directory computes the project root the
 * same, PHP-5.6-safe way instead of repeating dirname() chains.
 */

if (!defined('PTAH_CHARACTERIZATION_ROOT')) {
    $root = realpath(__DIR__ . '/../../..');
    if ($root === false) {
        fwrite(STDERR, "characterization bootstrap: cannot resolve project root from " . __DIR__ . PHP_EOL);
        exit(1);
    }
    define('PTAH_CHARACTERIZATION_ROOT', $root);
}
