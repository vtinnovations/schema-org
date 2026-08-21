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
 * Dependency-free test runner.
 *
 * Runs the suite with nothing but PHP, for environments where the project's
 * dev dependencies are not installed. When PHPUnit is available, prefer it —
 * the test classes are written against its API and run there unchanged.
 *
 * Usage: php tools/run-tests.php [filter]
 */

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Framework\TestCase;

require \dirname(__DIR__) . '/tests/bootstrap.php';

$filter = $argv[1] ?? '';

foreach (glob(\dirname(__DIR__) . '/tests/*Test.php') ?: [] as $file) {
    require_once $file;
}

$passed = 0;
$failed = 0;
$skipped = 0;
$failures = [];

foreach (get_declared_classes() as $class) {
    if (!is_subclass_of($class, TestCase::class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);

    if ($reflection->isAbstract() || !str_starts_with($class, 'VTinnovations\\SchemaOrg\\Tests\\')) {
        continue;
    }

    if ('' !== $filter && !str_contains($class, $filter)) {
        continue;
    }

    $short = $reflection->getShortName();
    echo "\n" . $short . "\n";

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $instance = $reflection->newInstance();
        $setUp = $reflection->getMethod('setUp');
        $tearDown = $reflection->getMethod('tearDown');
        $setUp->setAccessible(true);
        $tearDown->setAccessible(true);

        try {
            $setUp->invoke($instance);
            $method->invoke($instance);
            ++$passed;
            echo '  .  ' . $method->getName() . "\n";
        } catch (SkippedTestError $skip) {
            ++$skipped;
            echo '  s  ' . $method->getName() . ' (' . $skip->getMessage() . ")\n";
        } catch (AssertionFailedError | Throwable $error) {
            ++$failed;
            $failures[] = $short . '::' . $method->getName() . ' — ' . $error->getMessage();
            echo '  F  ' . $method->getName() . ' — ' . $error->getMessage() . "\n";
        } finally {
            try {
                $tearDown->invoke($instance);
            } catch (Throwable) {
                // A failing teardown must not mask the result above.
            }
        }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo sprintf("passed: %d   failed: %d   skipped: %d\n", $passed, $failed, $skipped);

if ([] !== $failures) {
    echo "\nFailures:\n";

    foreach ($failures as $failure) {
        echo '  - ' . $failure . "\n";
    }
}

exit($failed > 0 ? 1 : 0);
