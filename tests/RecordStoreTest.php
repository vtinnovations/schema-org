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
use VTinnovations\SchemaOrg\Intake\SealedPackage;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Storage\RecordStore;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;
use VTinnovations\SchemaOrg\Tests\Support\TempPaths;

final class RecordStoreTest extends TestCase
{
    private PackageFactory $factory;

    private TempPaths $temp;

    private RecordStore $store;

    private PackageOpener $opener;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
        $this->temp = new TempPaths();
        $this->opener = new PackageOpener($this->factory->anchors(), new ProductProfile());
        $this->store = new RecordStore($this->temp->paths, $this->opener, new ProductProfile());
    }

    protected function tearDown(): void
    {
        $this->temp->destroy();
    }

    public function testAnEmptyInstallationHoldsNothing(): void
    {
        self::assertNull($this->store->readPair());
        self::assertFalse($this->store->hasPair());
    }

    public function testCommitStoresTheExactBytesAndTheirEnvelope(): void
    {
        $package = $this->package();
        $this->store->commit($package, PackageFactory::NOW);

        $pair = $this->store->readPair();

        self::assertNotNull($pair);
        self::assertSame($package->bytes(), $pair['bytes']);
        self::assertSame($package->envelopeJson(), $pair['envelope']);

        // What was written verifies again from disk.
        $reopened = $this->opener->reopen($pair['envelope'], $pair['bytes'], PackageFactory::NOW);
        self::assertSame(7, $reopened->version());
    }

    public function testStateFilesAreNotReadableByEveryone(): void
    {
        if (str_starts_with(PHP_OS_FAMILY, 'Win')) {
            self::markTestSkipped('POSIX permissions do not apply here');
        }

        $this->store->commit($this->package(), PackageFactory::NOW);

        foreach ([$this->temp->paths->recordFile(), $this->temp->paths->sealFile()] as $file) {
            self::assertSame('0600', substr(sprintf('%o', fileperms($file)), -4), $file);
        }

        self::assertSame('0700', substr(sprintf('%o', fileperms($this->temp->paths->stateDir())), -4));
    }

    public function testANewerPackageReplacesTheStoredOneCompletely(): void
    {
        $this->store->commit($this->package(), PackageFactory::NOW);
        $this->store->commit($this->package(['license_version' => 8]), PackageFactory::NOW);

        $pair = $this->store->readPair();
        self::assertNotNull($pair);

        self::assertSame(8, $this->opener->reopen($pair['envelope'], $pair['bytes'], PackageFactory::NOW)->version());
        self::assertFileDoesNotExist($this->temp->paths->recordFile() . '.bak', 'no stale second copy is left behind');
        self::assertFileDoesNotExist($this->temp->paths->sealFile() . '.bak');
    }

    public function testACommitLeavesNoTemporaryFilesBehind(): void
    {
        $this->store->commit($this->package(), PackageFactory::NOW);

        self::assertCount(0, glob($this->temp->paths->stateDir() . '/*.tmp') ?: []);
    }

    public function testEditingTheStoredRecordIsDetectedOnTheNextRead(): void
    {
        $this->store->commit($this->package(), PackageFactory::NOW);

        file_put_contents(
            $this->temp->paths->recordFile(),
            str_replace('"free"', '"pro"', (string) file_get_contents($this->temp->paths->recordFile())),
        );

        $pair = $this->store->readPair();
        self::assertNotNull($pair);

        try {
            $this->opener->reopen($pair['envelope'], $pair['bytes'], PackageFactory::NOW);
            self::fail('An edited record must not reopen.');
        } catch (PackageRejected $rejected) {
            self::assertSame(PackageRejected::CHECKSUM_MISMATCH, $rejected->category());
        }
    }

    public function testRemovalTakesTheStateAndItsBackupsAway(): void
    {
        $this->store->commit($this->package(), PackageFactory::NOW);
        $this->store->discard();

        self::assertNull($this->store->readPair());
        self::assertFileDoesNotExist($this->temp->paths->recordFile());
        self::assertFileDoesNotExist($this->temp->paths->sealFile());
        self::assertFileDoesNotExist($this->temp->paths->recordFile() . '.bak');
        self::assertSame('', $this->store->sidecar()['key']);
    }

    public function testTheLockIsReentrant(): void
    {
        $reached = $this->store->withLock(fn (): string => (string) $this->store->withLock(static fn (): string => 'inner'));

        self::assertSame('inner', $reached);
    }

    public function testAKeyFromASupersededStoreIsAdoptedAndTheOldFileRemoved(): void
    {
        $legacy = $this->temp->paths->supersededStateFile();
        @mkdir(\dirname($legacy), 0700, true);
        file_put_contents($legacy, json_encode([
            'license_key' => 'VT-OLD-KEY-0001',
            'license_verified_at' => PackageFactory::NOW,
        ]));

        self::assertTrue($this->store->adoptSupersededState());
        self::assertSame('VT-OLD-KEY-0001', $this->store->sidecar()['key']);
        self::assertFileDoesNotExist($legacy, 'the superseded store must not survive as a second source of truth');

        // Adopting a key is not the same as being licensed.
        self::assertFalse($this->store->hasPair());
    }

    public function testASupersededStoreWithoutAUsableKeyIsSimplyRemoved(): void
    {
        $legacy = $this->temp->paths->supersededStateFile();
        @mkdir(\dirname($legacy), 0700, true);
        file_put_contents($legacy, json_encode(['license_key' => '']));

        self::assertFalse($this->store->adoptSupersededState());
        self::assertSame('', $this->store->sidecar()['key']);
        self::assertFileDoesNotExist($legacy);
    }

    public function testTheSidecarNeverBecomesState(): void
    {
        $this->store->writeSidecar(['key' => 'VT-PENDING-0001', 'category' => 'adopted_pending_activation']);

        self::assertSame('VT-PENDING-0001', $this->store->sidecar()['key']);
        self::assertFalse($this->store->hasPair());
        self::assertNull($this->store->readPair());
    }

    private function package(array $overrides = []): SealedPackage
    {
        $delivered = $this->factory->delivered($overrides);
        $host = HostName::tryFrom(PackageFactory::HOST);
        self::assertNotNull($host);

        return $this->opener->open($delivered['envelope'], $delivered['payload_b64'], $host, PackageFactory::NOW);
    }
}
