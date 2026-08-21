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
 * Every first-party source file states which package it belongs to, who holds
 * the copyright and under which licence it is distributed — and those values
 * come from composer.json rather than from whatever was copied in from another
 * project.
 */
final class SourceHeaderTest extends TestCase
{
    private const TREES = ['src', 'contao', 'tests', 'tools'];

    /** @var array<string, mixed> */
    private array $composer;

    protected function setUp(): void
    {
        $this->composer = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/composer.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );
    }

    public function testEveryFirstPartyFileCarriesTheProjectHeader(): void
    {
        foreach ($this->sources() as $path => $code) {
            $header = $this->header($code);

            self::assertNotNull($header, $path . ' has no project header');

            foreach (['Package:', 'Copyright:', 'Licence:', 'Website:'] as $line) {
                self::assertStringContainsString($line, $header, $path);
            }
        }
    }

    public function testThePackageAndLicenceMatchComposer(): void
    {
        foreach ($this->sources() as $path => $code) {
            $header = (string) $this->header($code);

            self::assertStringContainsString(' * Package: ' . $this->composer['name'], $header, $path);
            self::assertStringContainsString(' * Licence: ' . $this->composer['license'], $header, $path);
        }
    }

    public function testTheCopyrightHolderMatchesTheComposerAuthor(): void
    {
        $author = $this->composer['authors'][0]['name'] ?? null;

        self::assertNotNull($author, 'composer.json names no author');

        foreach ($this->sources() as $path => $code) {
            self::assertStringContainsString(' * Copyright: ' . $author, (string) $this->header($code), $path);
        }
    }

    public function testEveryFileNamesTheSameWebsite(): void
    {
        $websites = [];

        foreach ($this->sources() as $code) {
            if (1 === preg_match('/^ \* Website: (.+)$/m', (string) $this->header($code), $match)) {
                $websites[trim($match[1])] = true;
            }
        }

        self::assertCount(1, $websites, 'the headers disagree about the project website');

        // composer.json currently declares no homepage. If one is added, the
        // headers have to follow it rather than drift.
        if (isset($this->composer['homepage'])) {
            self::assertSame([$this->composer['homepage'] => true], $websites);
        }
    }

    public function testTheOldAnnotationStyleIsGone(): void
    {
        foreach ($this->sources() as $path => $code) {
            foreach (['@package', '@author', '@copyright', '@license'] as $tag) {
                self::assertStringNotContainsString($tag, (string) $this->header($code), $path);
            }
        }
    }

    public function testNoHeaderClaimsAnotherProject(): void
    {
        foreach ($this->sources() as $path => $code) {
            $header = (string) $this->header($code);

            foreach ([
                'Guardian',
                'Brickie',
                'contao-multilingual-pagetree',
                'This file is part of',
                'Leo Feyer',
                'proprietary',
                'MIT',
            ] as $foreign) {
                self::assertStringNotContainsString($foreign, $header, $path . ' names something foreign');
            }
        }
    }

    public function testGeneratedAndDependencyTreesAreLeftAlone(): void
    {
        foreach (['vendor', 'node_modules'] as $tree) {
            self::assertFalse(is_dir(\dirname(__DIR__) . '/' . $tree), $tree . ' must not be part of the checkout');
        }

        // Build output is generated, never edited and never committed.
        self::assertStringContainsString('/build/', (string) file_get_contents(\dirname(__DIR__) . '/.gitignore'));

        // Manifests and lock files never get comment headers.
        self::assertStringNotContainsString(
            'Package:',
            (string) file_get_contents(\dirname(__DIR__) . '/composer.json'),
        );
    }

    /**
     * The leading block comment of a file, or null when it has none.
     */
    private function header(string $code): ?string
    {
        foreach (token_get_all($code, TOKEN_PARSE) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if ((T_COMMENT === $id || T_DOC_COMMENT === $id) && str_starts_with($text, '/*')) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach (self::TREES as $tree) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(\dirname(__DIR__) . '/' . $tree, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }
}
