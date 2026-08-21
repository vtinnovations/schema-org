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
use VTinnovations\SchemaOrg\Remote\EndpointRegistry;

final class EndpointRegistryTest extends TestCase
{
    public function testTheDestinationsAreExactAndFixed(): void
    {
        $registry = new EndpointRegistry();

        self::assertSame('https://www.v-t.one/api/v1/verify', $registry->exchangeUrl());
        self::assertSame('https://www.v-t.one/rest/api/v1/log-envoke', $registry->signalUrl());
    }

    public function testNoOtherDestinationIsPermitted(): void
    {
        $registry = new EndpointRegistry();

        foreach ([
            'http://www.v-t.one/api/v1/verify',
            'https://v-t.one/api/v1/verify',
            'https://www.v-t.one:8443/api/v1/verify',
            'https://user@www.v-t.one/api/v1/verify',
            'https://www.v-t.one.evil.test/api/v1/verify',
            'https://example.com/api/v1/verify',
        ] as $url) {
            self::assertFalse($registry->isPermitted($url), $url);
        }

        self::assertTrue($registry->isPermitted($registry->exchangeUrl()));
        self::assertTrue($registry->isPermitted($registry->signalUrl()));
    }

    public function testTheAddressesAreNotReadableLiteralsInTheSource(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__) . '/src/Remote/EndpointRegistry.php');

        self::assertStringNotContainsString('https://www.v-t.one/api/v1/verify', $source);
        self::assertStringNotContainsString('https://www.v-t.one/rest/api/v1/log-envoke', $source);
    }
}
