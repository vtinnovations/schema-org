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
use VTinnovations\SchemaOrg\Tests\Support\SourceText;

/**
 * Structural checks.
 *
 * Shipped code can always be read; these assertions remove the shortcuts. No
 * folder announces itself, no single class holds the whole flow, and no single
 * registration can be removed to switch every check off.
 */
final class SourceLayoutTest extends TestCase
{
    private const ANNOUNCING_NAMES = [
        'Licensing', 'License', 'Licence', 'Protection', 'Integrity', 'AntiTamper', 'DRM', 'VtOne', 'VTone',
    ];

    private const ANNOUNCING_SYMBOLS = [
        'LicenseManager', 'LicenceManager', 'LicenseValidator', 'LicenseService', 'LicenseGuard',
        'LicenseVerifier', 'LicenseRepository', 'LicenseStateStore', 'LicenseIntegrityService',
        'LicenseUpdaterController', 'TamperDetector', 'AntiTamper', 'ExpectedMd5', 'ChecksumGuard',
        'VtoneLogger', 'VtOneClient',
    ];

    public function testNoDirectoryAnnouncesTheSubsystem(): void
    {
        foreach ($this->paths() as $path) {
            $relative = str_replace(\dirname(__DIR__) . '/', '', $path);

            foreach (self::ANNOUNCING_NAMES as $name) {
                self::assertFalse(
                    1 === preg_match('~(^|/)' . preg_quote($name, '~') . '(/|\.php$)~', $relative),
                    $relative . ' names the subsystem in its path',
                );
            }
        }
    }

    public function testNoClassAnnouncesTheSubsystem(): void
    {
        foreach ($this->sources() as $file => $code) {
            foreach (self::ANNOUNCING_SYMBOLS as $symbol) {
                self::assertStringNotContainsString($symbol, $code, $file);
            }
        }
    }

    public function testOurNamespacesDoNotAnnounceTheSubsystem(): void
    {
        foreach ($this->sources() as $file => $code) {
            if (1 !== preg_match('/^namespace\s+(.+);/m', $code, $match)) {
                continue;
            }

            foreach (self::ANNOUNCING_NAMES as $name) {
                self::assertStringNotContainsString('\\' . $name, $match[1], $file);
            }
        }
    }

    public function testTheSupersededImplementationIsGoneRatherThanHidden(): void
    {
        foreach ($this->sources() + $this->configs() as $file => $code) {
            foreach (['SchemaOrg\\Security', 'LicenseRefreshCron', 'SCHEMA_ORG_LICENSE_BYPASS', 'X-VT-Api-Key', 'vt_license_api_secret'] as $stale) {
                self::assertStringNotContainsString($stale, $code, $file . ' still refers to the replaced implementation');
            }
        }

        self::assertFileDoesNotExist(\dirname(__DIR__) . '/src/Security');
    }

    public function testTheResponsibilitiesAreSpreadAcrossTheArchitecture(): void
    {
        $responsibilities = [
            'src/Config/TrustAnchors.php',              // pinned keys
            'src/Config/ProductProfile.php',            // identity and tier rules
            'src/Serializer/CanonicalJson.php',         // canonical form
            'src/Serializer/DetachedSignature.php',     // signature checking
            'src/Intake/PackageOpener.php',             // package verification
            'src/Intake/RequestAuthorization.php',      // inbound request authentication
            'src/Site/HostName.php',                    // host policy
            'src/Site/StatusEvaluator.php',             // evaluation
            'src/Storage/RecordStore.php',              // persistence and rollback
            'src/Storage/ExchangeJournal.php',          // replay
            'src/Remote/EndpointRegistry.php',          // fixed destinations
            'src/Remote/UsageSignal.php',               // signals
        ];

        $directories = [];

        foreach ($responsibilities as $file) {
            self::assertFileExists(\dirname(__DIR__) . '/' . $file);
            $directories[\dirname($file)] = true;
        }

        self::assertGreaterThan(
            5,
            \count($directories),
            'the flow must not be readable from one directory listing',
        );
    }

    public function testNoSingleFileHoldsTheWholeFlow(): void
    {
        $marks = [
            'endpoint' => 'v-t.one',
            'keys' => 'PINNED',
            'checksum' => 'md5(',
            'signature' => 'sodium_crypto_sign_verify_detached',
            'hosts' => 'idn_to_ascii',
            'persistence' => 'rename(',
        ];

        foreach ($this->sources() as $file => $code) {
            // Only the code counts: the header names the company website in
            // every file and would otherwise match the endpoint mark.
            $body = SourceText::withoutComments($code);
            $present = array_keys(array_filter($marks, static fn (string $mark): bool => str_contains($body, $mark)));

            self::assertTrue(
                \count($present) <= 2,
                basename($file) . ' concentrates too much of the flow: ' . implode(', ', $present),
            );
        }
    }

    public function testEachProtectedBoundaryDecidesForItself(): void
    {
        $gates = [
            'src/EventListener/SchemaResponseListener.php',
            'src/Schema/SchemaBuilder.php',
            'src/Controller/PreviewController.php',
        ];

        foreach ($gates as $gate) {
            $code = (string) file_get_contents(\dirname(__DIR__) . '/' . $gate);

            self::assertStringContainsString('StatusEvaluator', $code, $gate . ' does not check anything');
            self::assertStringContainsString('isEntitled', $code, $gate . ' does not act on the check');
        }

        // The front end boundary adds its own host check on top of the shared
        // result, so removing one condition does not open everything.
        $listener = (string) file_get_contents(\dirname(__DIR__) . '/src/EventListener/SchemaResponseListener.php');
        self::assertStringContainsString('authorises(', $listener);
    }

    public function testTheUpdaterHandlerStaysThin(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__) . '/src/Controller/PackageIntakeController.php');

        foreach (['sodium_', 'md5(', 'base64_decode', 'file_put_contents', 'rename(', 'PINNED'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller, 'the public handler must delegate, not decide');
        }
    }

    public function testTheSourceContainsNoSigningMaterialOrDynamicExecution(): void
    {
        foreach ($this->sources() as $file => $code) {
            foreach ([
                'PRIVATE KEY',
                'sodium_crypto_sign_detached',
                'sodium_crypto_sign_secretkey',
                'eval(',
                'assert(',
                'create_function',
                'unserialize(',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $code, $file);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach ($this->paths() as $path) {
            if (str_ends_with($path, '.php')) {
                $sources[$path] = (string) file_get_contents($path);
            }
        }

        return $sources;
    }

    /**
     * @return array<string, string>
     */
    private function configs(): array
    {
        $configs = [];

        foreach (['config/services.yaml', 'config/routes.yaml', 'contao/dca/tl_settings.php'] as $file) {
            $path = \dirname(__DIR__) . '/' . $file;
            $configs[$path] = (string) file_get_contents($path);
        }

        return $configs;
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $paths = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__) . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }
}
