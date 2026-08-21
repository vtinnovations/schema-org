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
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Operation\KeyExchange;
use VTinnovations\SchemaOrg\Remote\ExchangeFailure;
use VTinnovations\SchemaOrg\Remote\PackageSource;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\DomainInventory;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\RecordStore;
use VTinnovations\SchemaOrg\Tests\Support\FakeInventory;
use VTinnovations\SchemaOrg\Tests\Support\FakeSource;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;
use VTinnovations\SchemaOrg\Tests\Support\RecordingLogger;
use VTinnovations\SchemaOrg\Tests\Support\TempPaths;

/**
 * What the operator's two buttons actually do, and — more importantly — what
 * they do not do when something goes wrong.
 */
final class KeyExchangeTest extends TestCase
{
    private PackageFactory $factory;

    private TempPaths $temp;

    private PackageOpener $opener;

    private RecordStore $store;

    private FakeSource $source;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
        $this->temp = new TempPaths();
        $this->opener = new PackageOpener($this->factory->anchors(), new ProductProfile());
        $this->store = new RecordStore($this->temp->paths, $this->opener, new ProductProfile());
        $this->source = new FakeSource();
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        $this->temp->destroy();
    }

    public function testActivationStoresTheVerifiedPackage(): void
    {
        $this->source->willReturn($this->factory->delivered());

        $outcome = $this->exchange()->activate(PackageFactory::LICENCE_KEY, PackageFactory::NOW);

        self::assertTrue($outcome->ok);
        self::assertTrue($this->store->hasPair());
        self::assertSame(PackageSource::ACTIVATE, $this->source->calls[0]['action']);
        self::assertSame('example.com', $this->source->calls[0]['host']);
        self::assertNull($this->source->calls[0]['version'], 'a first activation reports no current version');
    }

    public function testAKeyOfTheWrongShapeNeverReachesTheNetwork(): void
    {
        $outcome = $this->exchange()->activate('  ', PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(ExchangeFailure::KEY_SHAPE, $outcome->category);
        self::assertCount(0, $this->source->calls);
    }

    public function testWithoutAConfiguredHostThereIsNothingToActivateFor(): void
    {
        $outcome = $this->exchange(new FakeInventory([]))->activate(PackageFactory::LICENCE_KEY, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(ExchangeFailure::NO_HOST, $outcome->category);
        self::assertCount(0, $this->source->calls);
    }

    public function testAnUnreachableServerLeavesAWorkingLicenceAlone(): void
    {
        $this->givenActivated();
        $this->source->willFail(ExchangeFailure::TRANSPORT, true);

        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(ExchangeFailure::TRANSPORT, $outcome->category);
        self::assertSame(7, $this->storedVersion(), 'a dropped connection is not a revocation');
    }

    public function testARefusedAnswerAlsoLeavesThePreviousLicenceInPlace(): void
    {
        $this->givenActivated();
        $this->source->willFail(ExchangeFailure::DENIED, false);

        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(7, $this->storedVersion());
    }

    public function testAnAnswerForAnotherHostIsNotStored(): void
    {
        $this->givenActivated();

        $this->source->willReturn($this->factory->delivered([
            'license_version' => 9,
            'license_domain' => 'other.test',
            'license_domains' => ['other.test'],
        ]));

        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(PackageRejected::HOST_MISMATCH, $outcome->category);
        self::assertSame(7, $this->storedVersion());
    }

    public function testAnOlderPackageForTheSameKeyCannotRollTheInstallationBack(): void
    {
        $this->givenActivated();
        $this->source->willReturn($this->factory->delivered(['license_version' => 6]));

        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(PackageRejected::VERSION_ROLLBACK, $outcome->category);
        self::assertSame(7, $this->storedVersion());
    }

    public function testANewerPackageForTheSameKeyIsApplied(): void
    {
        $this->givenActivated();
        $this->source->willReturn($this->factory->delivered(['license_version' => 9]));

        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertTrue($outcome->ok);
        self::assertSame(9, $this->storedVersion());
    }

    public function testADifferentKeyIsNotJudgedAgainstTheOldVersionNumber(): void
    {
        $this->givenActivated();

        // Another licence entirely; its version counter is unrelated.
        $this->source->willReturn($this->factory->delivered([
            'license_key' => 'VT-SCHEMA-ORG-TEST-0002',
            'license_version' => 1,
        ]));

        $outcome = $this->exchange()->activate('VT-SCHEMA-ORG-TEST-0002', PackageFactory::NOW);

        self::assertTrue($outcome->ok);
        self::assertSame(1, $this->storedVersion());
    }

    public function testRefreshSendsTheStoredKeyAndTheCurrentVersion(): void
    {
        $this->givenActivated();
        $this->source->calls = [];
        $this->source->willReturn($this->factory->delivered(['license_version' => 8]));

        $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertSame(PackageSource::REFRESH, $this->source->calls[0]['action']);
        self::assertSame(PackageFactory::LICENCE_KEY, $this->source->calls[0]['key']);
        self::assertSame(7, $this->source->calls[0]['version']);
    }

    public function testRefreshCanCarryAReplacementKey(): void
    {
        $this->givenActivated();
        $this->source->calls = [];
        $this->source->willReturn($this->factory->delivered([
            'license_key' => 'VT-SCHEMA-ORG-TEST-0003',
            'license_version' => 8,
        ]));

        $this->exchange()->refresh('VT-SCHEMA-ORG-TEST-0003', PackageFactory::NOW);

        self::assertSame('VT-SCHEMA-ORG-TEST-0003', $this->source->calls[0]['key']);
    }

    public function testRefreshingWithoutAnythingStoredDoesNothing(): void
    {
        $outcome = $this->exchange()->refresh(null, PackageFactory::NOW);

        self::assertFalse($outcome->ok);
        self::assertSame(ExchangeFailure::NO_KEY, $outcome->category);
        self::assertCount(0, $this->source->calls);
    }

    public function testBackgroundRefreshUsesTheBoundHostRatherThanNone(): void
    {
        $this->givenActivated();
        $this->source->calls = [];
        $this->source->willReturn($this->factory->delivered(['license_version' => 8]));

        // No current request host at all, as in a cron run.
        $this->exchange(new FakeInventory(['www.example.com', 'example.com']))->refresh(null, PackageFactory::NOW);

        self::assertSame('example.com', $this->source->calls[0]['host']);
    }

    public function testNothingSensitiveIsLogged(): void
    {
        $this->source->willReturn($this->factory->delivered());
        $this->exchange()->activate(PackageFactory::LICENCE_KEY, PackageFactory::NOW);

        $logged = $this->logger->flatten();

        self::assertNotSame('', $logged);
        self::assertStringNotContainsString(PackageFactory::LICENCE_KEY, $logged);
        self::assertStringNotContainsString('license_md5', $logged);
        self::assertStringNotContainsString('signature', $logged);
        self::assertStringContainsString('license_version', $logged, 'the applied version is useful and safe');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function exchange(?DomainInventory $inventory = null): KeyExchange
    {
        $inventory ??= new FakeInventory(['example.com']);

        return new KeyExchange(
            $this->store,
            $this->source,
            $this->opener,
            new StatusEvaluator($this->store, $this->opener, $inventory),
            $inventory,
            new ProductProfile(),
            $this->logger,
        );
    }

    private function givenActivated(): void
    {
        $this->source->willReturn($this->factory->delivered());
        $outcome = $this->exchange()->activate(PackageFactory::LICENCE_KEY, PackageFactory::NOW);

        self::assertTrue($outcome->ok, 'fixture setup failed');
    }

    private function storedVersion(): ?int
    {
        $pair = $this->store->readPair();

        if (null === $pair) {
            return null;
        }

        return $this->opener->reopen($pair['envelope'], $pair['bytes'], PackageFactory::NOW)->version();
    }
}
