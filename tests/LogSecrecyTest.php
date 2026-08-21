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
 * Ordinary logs must not carry packet material.
 *
 * Packet bodies, nonces, checksums, signatures, payloads and anything derived
 * from a key, including its length or a digest, are excluded. Redacting only the
 * key does not make a packet dump safe, so the rule covers the whole call.
 *
 * The runtime side is asserted in the exchange and push tests, which capture
 * what those paths emit. This one reads the source, so a new log line cannot
 * slip in on a path the suite does not execute.
 */
final class LogSecrecyTest extends TestCase
{
    private const FORBIDDEN_IN_LOG_CALLS = [
        'nonce',
        'signature',
        'license_md5',
        'licence_md5',
        'payload',
        'request_body',
        'response_body',
        'request_packet',
        'response_packet',
        'sha256',
        'license_key',
        'licence_key',
        '->key()',
        'authenticatedKey',
        'maskedKey',
        '->bytes()',
        'envelopeJson',
        'getContent',
        'getMessage',
    ];

    private const LOG_METHODS = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log',
    ];

    public function testNoLogCallCarriesPacketMaterial(): void
    {
        foreach ($this->sources() as $file => $code) {
            foreach ($this->logCalls($code) as $call) {
                foreach (self::FORBIDDEN_IN_LOG_CALLS as $forbidden) {
                    self::assertStringNotContainsString(
                        $forbidden,
                        $call,
                        $file . ' logs something it must not: ' . $call,
                    );
                }
            }
        }
    }

    public function testEveryLogCallGoesThroughTheInjectedLogger(): void
    {
        // The scan above is anchored on "logger->", so a log written through
        // some other handle would escape it.
        foreach ($this->sources() as $file => $code) {
            self::assertSame(
                substr_count($code, 'logger->'),
                substr_count($code, '$this->logger->'),
                $file . ' writes to a logger the audit cannot see',
            );
        }
    }

    public function testTheSourceDoesNotPrintOrDumpAnything(): void
    {
        foreach ($this->sources() as $file => $code) {
            foreach (['error_log(', 'var_dump(', 'print_r(', 'var_export(', 'file_put_contents(\'php://'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $code, $file);
            }
        }
    }

    public function testLogMessagesAreOperationalRatherThanTranslated(): void
    {
        // Contao core writes plain English to its loggers, and so do we: a log
        // line is read by whoever runs the server, is grepped and shipped to
        // tooling, and must not change with the backend user's language.
        foreach ($this->sources() as $file => $code) {
            foreach ($this->logCalls($code) as $call) {
                self::assertStringNotContainsString('TL_LANG', $call, $file);
                self::assertStringNotContainsString('->label(', $call, $file);
                self::assertStringNotContainsString('->t(', $call, $file);
                self::assertStringNotContainsString('trans(', $call, $file);
            }
        }
    }

    public function testTheKeyIsNeverRenderedInFull(): void
    {
        $panel = (string) file_get_contents(\dirname(__DIR__) . '/src/DataContainer/SettingsPanel.php');
        $preview = (string) file_get_contents(\dirname(__DIR__) . '/src/Controller/PreviewController.php');

        // The screen shows the key only in the masked form the status already
        // carries — the same five facts the sibling V&T sections show — and
        // never fills the entry field from storage. Every read of a key on this
        // surface must therefore go through $status->maskedKey.
        preg_match_all('/maskedKey/', $panel, $reads);
        preg_match_all('/\$status->maskedKey/', $panel, $masked);
        self::assertCount(\count($reads[0]), $masked[0], 'the panel may only read the already-masked key');

        self::assertStringNotContainsString('authenticatedKey', $panel);
        self::assertStringNotContainsString('authenticatedKey', $preview);
        self::assertStringContainsString('value=""', $panel, 'the key field is never pre-filled from storage');
    }

    public function testOnlyTheSignalMayCarryTheKeyOutwards(): void
    {
        $callers = [];

        foreach ($this->sources() as $file => $code) {
            if (str_contains($code, 'authenticatedKey(')) {
                $callers[] = basename($file);
            }
        }

        sort($callers);

        self::assertSame(
            ['InstallationStatus.php', 'UsageSignal.php'],
            $callers,
            'the full key is read in one place only, and it is the session entry signal',
        );
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__) . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }

    /**
     * Every logger call in a file, as source text between its parentheses.
     *
     * @return list<string>
     */
    private function logCalls(string $code): array
    {
        $calls = [];

        foreach (self::LOG_METHODS as $method) {
            // Anchored on the logger itself, so an unrelated method that
            // happens to share a name is not mistaken for a log line.
            $needle = 'logger->' . $method . '(';
            $offset = 0;

            while (false !== $position = strpos($code, $needle, $offset)) {
                $start = $position + \strlen($needle);
                $depth = 1;
                $index = $start;

                while ($index < \strlen($code) && $depth > 0) {
                    $depth += match ($code[$index]) {
                        '(' => 1,
                        ')' => -1,
                        default => 0,
                    };

                    ++$index;
                }

                $calls[] = substr($code, $start, $index - $start - 1);
                $offset = $index;
            }
        }

        return $calls;
    }
}
