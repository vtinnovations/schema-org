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
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;

final class DetachedSignatureTest extends TestCase
{
    private PackageFactory $factory;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
    }

    public function testVerifiesABase64Signature(): void
    {
        $signature = $this->factory->sign('hello');

        self::assertTrue(DetachedSignature::verify('ed25519', 'hello', $signature, $this->factory->publicKey));
    }

    public function testVerifiesTheSameSignatureInHex(): void
    {
        $raw = base64_decode($this->factory->sign('hello'), true);

        self::assertTrue(DetachedSignature::verify('ed25519', 'hello', bin2hex((string) $raw), $this->factory->publicKey));
    }

    public function testRejectsAChangedMessage(): void
    {
        $signature = $this->factory->sign('hello');

        self::assertFalse(DetachedSignature::verify('ed25519', 'hello ', $signature, $this->factory->publicKey));
    }

    public function testRejectsAnotherKey(): void
    {
        $other = new PackageFactory('another-seed');

        self::assertFalse(DetachedSignature::verify('ed25519', 'hello', $this->factory->sign('hello'), $other->publicKey));
    }

    public function testRejectsAnAlgorithmOutsideTheAllowlist(): void
    {
        self::assertFalse(DetachedSignature::supports('rsa-pss'));
        self::assertFalse(DetachedSignature::verify('rsa-pss', 'hello', $this->factory->sign('hello'), $this->factory->publicKey));
    }

    public function testRejectsAKeyOfTheWrongLength(): void
    {
        self::assertFalse(DetachedSignature::verify('ed25519', 'hello', $this->factory->sign('hello'), 'short'));
    }

    public function testRejectsUndecodableSignatures(): void
    {
        self::assertNull(DetachedSignature::decode(''));
        self::assertNull(DetachedSignature::decode('not a signature'));
        self::assertNull(DetachedSignature::decode(base64_encode('too short')));
        self::assertNotNull(DetachedSignature::decode($this->factory->sign('hello')));
    }
}
