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
use VTinnovations\SchemaOrg\Storage\ExchangeJournal;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;
use VTinnovations\SchemaOrg\Tests\Support\TempPaths;

final class ExchangeJournalTest extends TestCase
{
    private TempPaths $temp;

    private ExchangeJournal $journal;

    protected function setUp(): void
    {
        $this->temp = new TempPaths();
        $this->journal = new ExchangeJournal($this->temp->paths);
    }

    protected function tearDown(): void
    {
        $this->temp->destroy();
    }

    public function testAnUnseenRequestIsFresh(): void
    {
        $seen = $this->journal->classify('req-1', 'nonce-1', 'body-hash', PackageFactory::NOW);

        self::assertSame(ExchangeJournal::FRESH, $seen['outcome']);
    }

    public function testAnIdenticalRetryIsRecognisedAsAlreadyApplied(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'body-hash', 9, PackageFactory::NOW);

        $seen = $this->journal->classify('req-1', 'nonce-1', 'body-hash', PackageFactory::NOW + 5);

        self::assertSame(ExchangeJournal::REPLAY, $seen['outcome']);
        self::assertSame(9, $seen['version'], 'the retry reports the version that was applied the first time');
    }

    public function testTheSameIdentifierWithDifferentContentIsAConflict(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'body-hash', 9, PackageFactory::NOW);

        self::assertSame(
            ExchangeJournal::CONFLICT,
            $this->journal->classify('req-1', 'nonce-1', 'another-body', PackageFactory::NOW)['outcome'],
        );

        self::assertSame(
            ExchangeJournal::CONFLICT,
            $this->journal->classify('req-1', 'another-nonce', 'body-hash', PackageFactory::NOW)['outcome'],
        );
    }

    public function testAReusedNonceUnderANewIdentifierIsAConflict(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'body-hash', 9, PackageFactory::NOW);

        self::assertSame(
            ExchangeJournal::CONFLICT,
            $this->journal->classify('req-2', 'nonce-1', 'other-body', PackageFactory::NOW)['outcome'],
        );
    }

    public function testTheNonceItselfIsNeverStored(): void
    {
        $this->journal->remember('req-1', 'super-secret-nonce', 'body-hash', 9, PackageFactory::NOW);

        $contents = (string) file_get_contents($this->temp->paths->journalFile());

        self::assertStringNotContainsString('super-secret-nonce', $contents);
        self::assertStringContainsString(hash('sha256', 'super-secret-nonce'), $contents);
    }

    public function testRecordsExpireSoTheJournalDoesNotGrowForever(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'body-hash', 9, PackageFactory::NOW);

        $muchLater = PackageFactory::NOW + 31 * 86400;
        $this->journal->prune($muchLater);

        self::assertSame(
            ExchangeJournal::FRESH,
            $this->journal->classify('req-1', 'nonce-1', 'body-hash', $muchLater)['outcome'],
        );
    }

    public function testTheReplayWindowOutlivesTheRetryWindow(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'body-hash', 9, PackageFactory::NOW);

        // Well past any sane retry, still remembered.
        self::assertSame(
            ExchangeJournal::REPLAY,
            $this->journal->classify('req-1', 'nonce-1', 'body-hash', PackageFactory::NOW + 6 * 86400)['outcome'],
        );
    }
}
