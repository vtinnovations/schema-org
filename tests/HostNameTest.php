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
use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Hosts are compared byte for byte. Normalisation may change how a name is
 * written; it may never change which name it is.
 */
final class HostNameTest extends TestCase
{
    public function testNormalisesRepresentationOnly(): void
    {
        foreach ([
            'Example.COM' => 'example.com',
            'example.com.' => 'example.com',
            'example.com:8443' => 'example.com',
            'WWW.Example.com:443' => 'www.example.com',
            '  example.com  ' => 'example.com',
        ] as $input => $expected) {
            self::assertSame($expected, (string) HostName::tryFrom((string) $input)?->toString(), (string) $input);
        }
    }

    public function testConvertsInternationalisedNamesConsistently(): void
    {
        if (!\function_exists('idn_to_ascii')) {
            self::markTestSkipped('ext-intl is not available');
        }

        $unicode = HostName::tryFrom('müller.example.com');
        $punycode = HostName::tryFrom('xn--mller-kva.example.com');

        self::assertNotNull($unicode);
        self::assertNotNull($punycode);
        self::assertTrue($unicode->equals($punycode));
    }

    public function testTreatsRelatedNamesAsDifferentIdentities(): void
    {
        $apex = HostName::tryFrom('example.com');
        $www = HostName::tryFrom('www.example.com');
        $shop = HostName::tryFrom('shop.example.com');
        $nested = HostName::tryFrom('admin.shop.example.com');
        $sibling = HostName::tryFrom('example.com.evil.test');

        foreach ([$www, $shop, $nested, $sibling] as $other) {
            self::assertFalse($apex?->equals($other) ?? false);
            self::assertFalse($other?->equals($apex) ?? false);
        }

        self::assertFalse($shop?->equals($nested) ?? false);
    }

    public function testRefusesAnythingThatIsNotOneExactName(): void
    {
        foreach ([
            '',
            '*',
            '*.example.com',
            'example.com/path',
            'https://example.com',
            'user@example.com',
            'exa mple.com',
            'localhost',
            '127.0.0.1',
            '::1',
            '[2001:db8::1]',
            '1.2.3.4',
            'example..com',
            '-example.com',
            'example.com:notaport',
            str_repeat('a', 64) . '.example.com',
        ] as $candidate) {
            self::assertNull(HostName::tryFrom($candidate), $candidate);
        }
    }

    public function testSetOperationsAreExact(): void
    {
        $configured = HostName::unique(array_filter([
            HostName::tryFrom('www.example.com'),
            HostName::tryFrom('example.com'),
            HostName::tryFrom('example.com'),
        ]));

        self::assertCount(2, $configured);
        self::assertSame('example.com', $configured[0]->toString(), 'unique() sorts bytewise');

        $signed = array_filter([HostName::tryFrom('example.com'), HostName::tryFrom('other.test')]);
        $shared = HostName::intersect($configured, array_values($signed));

        self::assertCount(1, $shared);
        self::assertSame('example.com', $shared[0]->toString());

        self::assertTrue(HostName::contains($configured, HostName::tryFrom('www.example.com')));
        self::assertFalse(HostName::contains($configured, HostName::tryFrom('shop.example.com')));
    }
}
