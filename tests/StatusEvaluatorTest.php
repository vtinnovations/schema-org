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
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\SealedPackage;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\DomainInventory;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\InstallationStatus;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\RecordStore;
use VTinnovations\SchemaOrg\Tests\Support\FakeInventory;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;
use VTinnovations\SchemaOrg\Tests\Support\TempPaths;

/**
 * Whether this installation may do the licensed thing, and for which host.
 */
final class StatusEvaluatorTest extends TestCase
{
    private PackageFactory $factory;

    private TempPaths $temp;

    private PackageOpener $opener;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
        $this->temp = new TempPaths();
        $this->opener = new PackageOpener($this->factory->anchors(), new ProductProfile());
    }

    protected function tearDown(): void
    {
        $this->temp->destroy();
    }

    public function testAnInstallationWithoutAPackageIsNotEntitled(): void
    {
        $status = $this->evaluator(new FakeInventory())->current(PackageFactory::NOW);

        self::assertSame(InstallationStatus::ABSENT, $status->state);
        self::assertSame('no_state', $status->category);
        self::assertFalse($status->isEntitled());
        self::assertNull($status->authenticatedKey());
    }

    public function testAValidPackageForAConfiguredHostIsEntitled(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        $status = $this->evaluator(new FakeInventory(['example.com']), $store)->current(PackageFactory::NOW);

        self::assertTrue($status->isEntitled());
        self::assertSame('example.com', $status->matchedHost);
        self::assertSame('free', $status->tier);
        self::assertTrue($status->perpetual);
        self::assertSame(PackageFactory::LICENCE_KEY, $status->authenticatedKey());
    }

    public function testTheMatchedHostPrefersTheCurrentTrustedHostWhenItIsAuthorised(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        $status = $this->evaluator(
            new FakeInventory(['example.com', 'www.example.com'], 'www.example.com'),
            $store,
        )->current(PackageFactory::NOW);

        self::assertSame('www.example.com', $status->matchedHost);
    }

    public function testTheMatchedHostIsDeterministicWithoutARequest(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        // No current host: background work still gets one fixed answer.
        $status = $this->evaluator(new FakeInventory(['www.example.com', 'example.com']), $store)->current(PackageFactory::NOW);

        self::assertSame('example.com', $status->matchedHost);
    }

    public function testAPackageForAnotherHostDoesNotAuthoriseThisOne(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        // The state was copied to an installation serving a different name.
        $status = $this->evaluator(new FakeInventory(['other.test']), $store)->current(PackageFactory::NOW);

        self::assertFalse($status->isEntitled());
        self::assertSame('host_not_configured', $status->category);
        self::assertNull($status->authenticatedKey());
    }

    public function testAnInstallationWithNoConfiguredHostIsNotEntitled(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        $status = $this->evaluator(new FakeInventory([]), $store)->current(PackageFactory::NOW);

        self::assertFalse($status->isEntitled());
        self::assertSame('no_configured_host', $status->category);
    }

    public function testAnEditedStoredRecordIsNotEntitled(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        file_put_contents(
            $this->temp->paths->recordFile(),
            str_replace('"free"', '"pro"', (string) file_get_contents($this->temp->paths->recordFile())),
        );

        $status = $this->evaluator(new FakeInventory(['example.com']), $store)->current(PackageFactory::NOW);

        self::assertFalse($status->isEntitled());
        self::assertSame('checksum_mismatch', $status->category);
    }

    public function testAuthorisationIsExactPerHost(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        $status = $this->evaluator(new FakeInventory(['example.com']), $store)->current(PackageFactory::NOW);

        self::assertTrue($status->authorises($this->host('example.com')));

        // Signed but not configured here.
        self::assertFalse($status->authorises($this->host('www.example.com')));

        // Neither signed nor configured.
        self::assertFalse($status->authorises($this->host('shop.example.com')));
        self::assertFalse($status->authorises($this->host('example.com.evil.test')));
    }

    public function testAnInstanceWideAllowanceStillOnlyAuthorisesSignedHosts(): void
    {
        $store = $this->store();
        $store->commit($this->package(['license_max_domains' => 9999]), PackageFactory::NOW);

        $status = $this->evaluator(new FakeInventory(['example.com', 'shop.example.com']), $store)->current(PackageFactory::NOW);

        self::assertTrue($status->isEntitled());
        self::assertFalse($status->authorises($this->host('shop.example.com')));
    }

    public function testTheLogContextCarriesNothingSensitive(): void
    {
        $store = $this->store();
        $store->commit($this->package(), PackageFactory::NOW);

        $status = $this->evaluator(new FakeInventory(['example.com']), $store)->current(PackageFactory::NOW);
        $context = json_encode($status->logContext()) ?: '';

        self::assertStringNotContainsString(PackageFactory::LICENCE_KEY, $context);
        self::assertStringNotContainsString('example.com', $context);
        self::assertStringContainsString('active', $context);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function store(): RecordStore
    {
        return new RecordStore($this->temp->paths, $this->opener, new ProductProfile());
    }

    private function evaluator(DomainInventory $inventory, ?RecordStore $store = null): StatusEvaluator
    {
        return new StatusEvaluator($store ?? $this->store(), $this->opener, $inventory);
    }

    private function package(array $overrides = []): SealedPackage
    {
        $delivered = $this->factory->delivered($overrides);

        return $this->opener->open($delivered['envelope'], $delivered['payload_b64'], $this->host(PackageFactory::HOST), PackageFactory::NOW);
    }

    private function host(string $host): HostName
    {
        $resolved = HostName::tryFrom($host);
        self::assertNotNull($resolved);

        return $resolved;
    }
}
