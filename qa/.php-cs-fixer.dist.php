<?php

// Style gate — @PSR12 blocking on ported CI4 code (PTAH MIG-09 ratchet;
// report-only before H5). Views keep template idiom (alternative syntax,
// inline PHP) and are covered by the E2E render gate instead.
$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/../app/Controllers',
        __DIR__ . '/../app/Repositories',
        __DIR__ . '/../tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true])
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setFinder($finder);
