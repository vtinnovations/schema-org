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
 * Minimal PHPUnit stand-in, loaded only when PHPUnit is not installed.
 *
 * The test classes are written against the real PHPUnit API, so once the
 * project's dev dependencies are installed the same files run under `phpunit`
 * unchanged and this file is never loaded.
 */

namespace PHPUnit\Framework {
    if (!class_exists(AssertionFailedError::class, false)) {
        class AssertionFailedError extends \RuntimeException
        {
        }

        class SkippedTestError extends \RuntimeException
        {
        }

        abstract class TestCase
        {
            protected function setUp(): void
            {
            }

            protected function tearDown(): void
            {
            }

            public static function fail(string $message = 'Failed.'): never
            {
                throw new AssertionFailedError($message);
            }

            public static function markTestSkipped(string $message = ''): never
            {
                throw new SkippedTestError($message);
            }

            public static function assertTrue(mixed $condition, string $message = ''): void
            {
                if (true !== $condition) {
                    self::fail($message ?: 'Expected true, got ' . self::export($condition) . '.');
                }
            }

            public static function assertFalse(mixed $condition, string $message = ''): void
            {
                if (false !== $condition) {
                    self::fail($message ?: 'Expected false, got ' . self::export($condition) . '.');
                }
            }

            public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
            {
                if ($expected !== $actual) {
                    self::fail($message ?: 'Expected ' . self::export($expected) . ', got ' . self::export($actual) . '.');
                }
            }

            public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
            {
                if ($expected === $actual) {
                    self::fail($message ?: 'Expected a value other than ' . self::export($expected) . '.');
                }
            }

            public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
            {
                if ($expected != $actual) {
                    self::fail($message ?: 'Expected ' . self::export($expected) . ', got ' . self::export($actual) . '.');
                }
            }

            public static function assertNotFalse(mixed $actual, string $message = ''): void
            {
                if (false === $actual) {
                    self::fail($message ?: 'Expected a value other than false.');
                }
            }

            public static function assertNull(mixed $actual, string $message = ''): void
            {
                if (null !== $actual) {
                    self::fail($message ?: 'Expected null, got ' . self::export($actual) . '.');
                }
            }

            public static function assertNotNull(mixed $actual, string $message = ''): void
            {
                if (null === $actual) {
                    self::fail($message ?: 'Expected a value, got null.');
                }
            }

            public static function assertCount(int $expected, \Countable|array $haystack, string $message = ''): void
            {
                if (\count($haystack) !== $expected) {
                    self::fail($message ?: 'Expected ' . $expected . ' items, got ' . \count($haystack) . '.');
                }
            }

            public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
            {
                if (!$actual instanceof $expected) {
                    self::fail($message ?: 'Expected an instance of ' . $expected . '.');
                }
            }

            public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
            {
                if (!str_contains($haystack, $needle)) {
                    self::fail($message ?: 'Expected the text to contain "' . $needle . '".');
                }
            }

            public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
            {
                if (str_contains($haystack, $needle)) {
                    self::fail($message ?: 'Expected the text not to contain "' . $needle . '".');
                }
            }

            public static function assertFileExists(string $file, string $message = ''): void
            {
                if (!is_file($file)) {
                    self::fail($message ?: 'Expected the file "' . $file . '" to exist.');
                }
            }

            public static function assertFileDoesNotExist(string $file, string $message = ''): void
            {
                if (is_file($file)) {
                    self::fail($message ?: 'Expected the file "' . $file . '" not to exist.');
                }
            }

            public static function assertEmpty(mixed $actual, string $message = ''): void
            {
                if (!empty($actual)) {
                    self::fail($message ?: 'Expected an empty value.');
                }
            }

            public static function assertNotEmpty(mixed $actual, string $message = ''): void
            {
                if (empty($actual)) {
                    self::fail($message ?: 'Expected a non-empty value.');
                }
            }

            public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
            {
                if (!($actual > $expected)) {
                    self::fail($message ?: 'Expected a value greater than ' . self::export($expected) . '.');
                }
            }

            private static function export(mixed $value): string
            {
                return match (true) {
                    \is_string($value) => '"' . $value . '"',
                    \is_bool($value) => $value ? 'true' : 'false',
                    null === $value => 'null',
                    \is_scalar($value) => (string) $value,
                    \is_array($value) => 'array(' . \count($value) . ')',
                    \is_object($value) => $value::class,
                    default => \gettype($value),
                };
            }
        }
    }
}
