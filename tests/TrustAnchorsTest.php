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
use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;

/**
 * A shipped build with no usable verification material could not tell a genuine
 * package from any other, so the state of this ring is a release condition, not
 * a detail.
 */
final class TrustAnchorsTest extends TestCase
{
    public function testTheShippedRingIsNotEmpty(): void
    {
        $anchors = new TrustAnchors();

        self::assertFalse($anchors->isEmpty(), 'A production build must pin verification material.');
        self::assertSame(['vtone-2026a'], $anchors->identifiers());
    }

    public function testTheShippedKeyMatchesItsRecordedDigest(): void
    {
        $key = (new TrustAnchors())->resolve('vtone-2026a', 'ed25519', TrustAnchors::USE_ENVELOPE, PackageFactory::NOW);

        self::assertNotNull($key);
        self::assertSame(DetachedSignature::PUBLIC_KEY_BYTES, \strlen((string) $key));
        self::assertSame('edcd614e70c59ce0', substr(hash('sha256', (string) $key), 0, 16));
    }

    public function testTheShippedKeyServesAllThreePurposes(): void
    {
        $anchors = new TrustAnchors();

        foreach ([TrustAnchors::USE_RECORD, TrustAnchors::USE_ENVELOPE, TrustAnchors::USE_REQUEST] as $use) {
            self::assertNotNull($anchors->resolve('vtone-2026a', 'ed25519', $use, PackageFactory::NOW));
            self::assertCount(1, $anchors->usableFor($use, PackageFactory::NOW));
        }
    }

    public function testAnUnknownIdentifierResolvesToNothing(): void
    {
        self::assertNull((new TrustAnchors())->resolve('vtone-2099z', 'ed25519', TrustAnchors::USE_ENVELOPE, PackageFactory::NOW));
    }

    public function testAnIdentifierPairedWithTheWrongAlgorithmResolvesToNothing(): void
    {
        self::assertNull((new TrustAnchors())->resolve('vtone-2026a', 'rsa-pss', TrustAnchors::USE_ENVELOPE, PackageFactory::NOW));
    }

    public function testAKeyOutsideItsWindowIsNotUsable(): void
    {
        $factory = new PackageFactory();
        $retired = $factory->anchors(PackageFactory::NOW - 1);

        self::assertNull($retired->resolve(PackageFactory::KEY_ID, 'ed25519', TrustAnchors::USE_RECORD, PackageFactory::NOW));
        self::assertCount(0, $retired->usableFor(TrustAnchors::USE_RECORD, PackageFactory::NOW));

        $current = $factory->anchors(PackageFactory::NOW + 1);
        self::assertNotNull($current->resolve(PackageFactory::KEY_ID, 'ed25519', TrustAnchors::USE_RECORD, PackageFactory::NOW));
    }

    public function testAnEmptyRingIsOnlyPossibleAsAnExplicitSubstitute(): void
    {
        $empty = new TrustAnchors([]);

        self::assertTrue($empty->isEmpty());
        self::assertNull($empty->resolve('vtone-2026a', 'ed25519', TrustAnchors::USE_ENVELOPE, PackageFactory::NOW));
    }

    public function testAStructurallyInvalidSubstituteIsRefused(): void
    {
        foreach ([
            ['alg' => 'ed25519', 'key' => 'far too short'],
            ['alg' => 'rsa-pss', 'key' => str_repeat('k', 32)],
            ['alg' => 'ed25519', 'key' => ''],
        ] as $index => $anchor) {
            try {
                new TrustAnchors(['broken-' . $index => $anchor]);
                self::fail('An unusable anchor must be refused at construction.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
