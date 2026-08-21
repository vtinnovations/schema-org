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

namespace VTinnovations\SchemaOrg\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Checks the artefact that actually ships, not the checkout it was built from.
 *
 * The builder runs the suite before it copies anything, so this invokes it with
 * that step skipped — otherwise the suite would be waiting on itself.
 */
final class ReleaseBuildTest extends TestCase
{
    private string $target = '';

    protected function tearDown(): void
    {
        if ('' !== $this->target && is_dir($this->target)) {
            $this->removeTree($this->target);
        }
    }

    public function testTheBuiltArtefactIsShippableAndComplete(): void
    {
        $relative = 'build/test-' . bin2hex(random_bytes(4));
        $this->target = \dirname(__DIR__) . '/' . $relative;

        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(\dirname(__DIR__) . '/tools/build-release.php')
            . ' --out=' . escapeshellarg($relative) . ' --skip-tests 2>&1';

        exec($command, $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertFileExists($this->target . '/release-manifest.json');

        // Nothing that is not the product may ship.
        foreach (['tests', 'tools', 'build', 'vendor'] as $unwanted) {
            self::assertFalse(is_dir($this->target . '/' . $unwanted), $unwanted . ' must not ship');
        }

        // Everything the framework resolves by name has to survive.
        self::assertStringContainsString(
            '/rest/api/v1/schema-org-license-updater',
            (string) file_get_contents($this->target . '/config/routes.yaml'),
        );
        self::assertStringContainsString(
            'Schema.org Licence management',
            (string) file_get_contents($this->target . '/contao/languages/en/tl_settings.php'),
        );

        // The stripped files still parse, and the fixed material is still not
        // readable in them.
        foreach ($this->builtFiles() as $file) {
            if (str_ends_with($file, '.php')) {
                token_get_all((string) file_get_contents($file), TOKEN_PARSE);
            }

            $contents = (string) file_get_contents($file);

            self::assertStringNotContainsString('https://www.v-t.one/api/v1/verify', $contents, $file);
            self::assertStringNotContainsString('qllgm+66FUVBFJ3O68ICFG8b37dR+9jMfr1+4/pSygE=', $contents, $file);
        }

        $this->assertManifestMatches();
    }

    private function assertManifestMatches(): void
    {
        /** @var array{files: array<string, string>, summary: string} $manifest */
        $manifest = json_decode((string) file_get_contents($this->target . '/release-manifest.json'), true, 8, JSON_THROW_ON_ERROR);

        self::assertNotEmpty($manifest['files']);

        foreach ($manifest['files'] as $path => $digest) {
            $file = $this->target . '/' . $path;

            self::assertFileExists($file);
            self::assertSame($digest, hash_file('sha256', $file), $path);
        }

        self::assertSame(64, \strlen($manifest['summary']));
    }

    /**
     * @return list<string>
     */
    private function builtFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->target, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 'release-manifest.json' !== $file->getFilename()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function removeTree(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
