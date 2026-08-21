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
use VTinnovations\SchemaOrg\Serializer\CanonicalJson;

/**
 * The canonical form is what both sides sign, so a difference of one byte here
 * is a difference of every signature.
 */
final class CanonicalJsonTest extends TestCase
{
    public function testSortsObjectMembersRecursivelyAndKeepsListOrder(): void
    {
        $document = CanonicalJson::decodeObject('{"b":{"z":1,"a":2},"a":["z","a","m"]}');

        self::assertSame('{"a":["z","a","m"],"b":{"a":2,"z":1}}', CanonicalJson::encode($document));
    }

    public function testRemovesOnlyTheTopLevelSignature(): void
    {
        $document = CanonicalJson::decodeObject('{"signature":"outer","inner":{"signature":"kept"},"a":1}');

        self::assertSame('{"a":1,"inner":{"signature":"kept"}}', CanonicalJson::signedForm($document));
    }

    public function testLeavesSlashesAndUnicodeUnescaped(): void
    {
        $document = CanonicalJson::decodeObject('{"url":"https://www.v-t.one/api","name":"Müller"}');

        self::assertSame('{"name":"Müller","url":"https://www.v-t.one/api"}', CanonicalJson::encode($document));
    }

    public function testPreservesScalarTypes(): void
    {
        $document = CanonicalJson::decodeObject('{"t":true,"f":false,"n":null,"i":0,"s":"false"}');

        self::assertSame('{"f":false,"i":0,"n":null,"s":"false","t":true}', CanonicalJson::encode($document));
    }

    public function testDistinguishesEmptyObjectFromEmptyList(): void
    {
        $document = CanonicalJson::decodeObject('{"o":{},"l":[]}');

        self::assertSame('{"l":[],"o":{}}', CanonicalJson::encode($document));
    }

    public function testFixedVector(): void
    {
        $bytes = '{"license_domains":["a.example.com","b.example.com"],"license_lifetime":true,'
            . '"license_expires_at":null,"project":"SchemaOrg","signature":"ignored"}';

        $expected = '{"license_domains":["a.example.com","b.example.com"],"license_expires_at":null,'
            . '"license_lifetime":true,"project":"SchemaOrg"}';

        self::assertSame($expected, CanonicalJson::signedForm(CanonicalJson::decodeObject($bytes)));
        self::assertSame(
            'b2915e2ab1ddb63e11dae9f45bb3bb929a8b1bcf6e0e05a321464e896cb35a0f',
            hash('sha256', $expected),
            'The canonical bytes of this fixture changed; every signature over it would too.',
        );
    }

    public function testRejectsNonObjectInput(): void
    {
        try {
            CanonicalJson::decodeObject('[1,2,3]');
            self::fail('A JSON list is not a document.');
        } catch (\JsonException) {
            self::assertTrue(true);
        }
    }
}
