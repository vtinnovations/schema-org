<?php

declare(strict_types=1);

/*
 * Schema.org Structured Data
 *
 * Package: vtinnovations/schema-org
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/**
 * Test bootstrap.
 *
 * When the project's dependencies are installed, Composer's autoloader and the
 * real PHPUnit are used and everything below is skipped. Without them, this
 * file registers a PSR-4 autoloader and defines the small subset of PHPUnit and
 * PSR-3 that the suite touches, so the parts of the extension that do not need
 * a framework can still be executed with nothing but PHP. Run those with
 * `php tools/run-tests.php`.
 *
 * The stubs never load when the real packages are present, and they are not
 * shipped in a release.
 */

$vendorAutoload = \dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

spl_autoload_register(static function (string $class): void {
    $roots = [
        'VTinnovations\\SchemaOrg\\Tests\\' => __DIR__ . '/',
        'VTinnovations\\SchemaOrg\\' => \dirname(__DIR__) . '/src/',
    ];

    foreach ($roots as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;

            return;
        }
    }
});

if (!interface_exists(Psr\Log\LoggerInterface::class)) {
    require __DIR__ . '/Stub/psr-log.php';
}

if (!class_exists(PHPUnit\Framework\TestCase::class)) {
    require __DIR__ . '/Stub/phpunit.php';
}

if (!class_exists(Contao\CoreBundle\DataContainer\PaletteManipulator::class)) {
    require __DIR__ . '/Stub/contao-dca.php';
}
