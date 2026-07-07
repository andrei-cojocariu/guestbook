<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap for this CodeIgniter 4 app: defines the framework path
 * and environment constants that the runtime front controller/boot files set,
 * so static analysis resolves them instead of reporting undefined constants.
 *
 * Also raises the PHP memory limit: level 9 + strict-rules over CI4's
 * BaseConfig hierarchy exceeds the analysis image's 128M default. Loaded in
 * every PHPStan process (including parallel workers), so the cap applies there.
 */

ini_set('memory_limit', '1G');

$root = __DIR__ . DIRECTORY_SEPARATOR;

// ENVIRONMENT / CI_DEBUG are declared dynamic in phpstan.neon
// (dynamicConstantNames) so their per-deployment nature is respected and the
// framework's environment guards are not constant-folded.
defined('ENVIRONMENT') || define('ENVIRONMENT', 'testing');
defined('CI_DEBUG') || define('CI_DEBUG', false);
defined('ROOTPATH') || define('ROOTPATH', $root);
defined('APPPATH') || define('APPPATH', $root . 'app' . DIRECTORY_SEPARATOR);
defined('SYSTEMPATH') || define('SYSTEMPATH', $root . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
defined('WRITEPATH') || define('WRITEPATH', $root . 'writable' . DIRECTORY_SEPARATOR);
defined('FCPATH') || define('FCPATH', $root . 'public' . DIRECTORY_SEPARATOR);
