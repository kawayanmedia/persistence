<?php

declare(strict_types=1);

/**
 * Resolves the Composer autoloader whether the package is run standalone or as
 * a path repository inside a host project.
 */
$candidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

$loader = null;

foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $loader = require $candidate;
        break;
    }
}

if ($loader === null) {
    fwrite(STDERR, "Composer autoloader not found. Run composer install.\n");
    exit(1);
}

$loader->addPsr4('KawayanMedia\\Persistence\\Tests\\', __DIR__);
