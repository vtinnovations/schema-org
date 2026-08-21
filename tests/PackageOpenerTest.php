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
use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Serializer\CanonicalJson;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;

/**
 * The checks a delivered package has to survive.
 *
 * Each case covers one way a package can be wrong and asserts that it cannot be
 * made to look acceptable.
 */
final class PackageOpenerTest extends TestCase
{
    private PackageFactory $factory;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
    }

    public function testAcceptsAPerpetualNoChargePackage(): void
    {
        $delivered = $this->factory->delivered();
        $package = $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);

        self::assertSame(PackageFactory::LICENCE_KEY, $package->key());
        self::assertSame('free', $package->tier());
        self::assertTrue($package->isPerpetual());
        self::assertNull($package->endsAt());
        self::assertSame(7, $package->version());
        self::assertSame('example.com', $package->operationHost()->toString());
        self::assertCount(2, $package->hosts());
        self::assertSame($delivered['bytes'], $package->bytes(), 'the exact delivered bytes are what is kept');
        self::assertStringContainsString('••••', $package->maskedKey());
    }

    public function testTheStoredPairReopensWithoutTheOperationHost(): void
    {
        $delivered = $this->factory->delivered();
        $opener = $this->opener();
        $package = $opener->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);

        $reopened = $opener->reopen($package->envelopeJson(), $package->bytes(), PackageFactory::NOW);

        self::assertSame($package->version(), $reopened->version());
        self::assertSame($package->key(), $reopened->key());
    }

    // ── the record cannot be edited ──────────────────────────────────────

    public function testASingleAddedSpaceBreaksTheChecksum(): void
    {
        $delivered = $this->factory->delivered();

        $this->reject(PackageRejected::CHECKSUM_MISMATCH, function () use ($delivered): void {
            $this->opener()->open($delivered['envelope'], base64_encode($delivered['bytes'] . ' '), $this->host(), PackageFactory::NOW);
        });
    }

    public function testEditingTheRecordAndRecomputingItsChecksumIsNotEnough(): void
    {
        // The attacker edits the record and produces a matching checksum, but
        // cannot re-sign the envelope.
        $edited = str_replace('"free"', '"pro"', $this->factory->recordBytes());
        $envelope = $this->factory->envelope($edited, ['license_md5' => md5($edited), 'signature' => 'AAAA']);

        $this->reject(PackageRejected::ENVELOPE_SIGNATURE_INVALID, function () use ($envelope, $edited): void {
            $this->opener()->open($envelope, base64_encode($edited), $this->host(), PackageFactory::NOW);
        });
    }

    public function testEditingTheRecordUnderAGenuineEnvelopeSignatureFailsTheChecksum(): void
    {
        $delivered = $this->factory->delivered();
        $edited = str_replace('"free"', '"pro0"', $delivered['bytes']);

        $this->reject(PackageRejected::CHECKSUM_MISMATCH, function () use ($delivered, $edited): void {
            $this->opener()->open($delivered['envelope'], base64_encode($edited), $this->host(), PackageFactory::NOW);
        });
    }

    public function testARecordWithABrokenSignatureIsRefused(): void
    {
        $bytes = $this->factory->recordBytes(['signature' => $this->factory->sign('something else')]);
        $envelope = $this->factory->envelope($bytes);

        $this->reject(PackageRejected::RECORD_SIGNATURE_INVALID, function () use ($envelope, $bytes): void {
            $this->opener()->open($envelope, base64_encode($bytes), $this->host(), PackageFactory::NOW);
        });
    }

    // ── key selection ────────────────────────────────────────────────────

    public function testAnEmptyRingCannotVerifyAnything(): void
    {
        $delivered = $this->factory->delivered();

        $this->reject(PackageRejected::ANCHOR_STORE_EMPTY, function () use ($delivered): void {
            $this->opener(new TrustAnchors([]))->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);
        });
    }

    public function testAnUnadvertisedKeyIdentifierIsRefused(): void
    {
        $delivered = $this->factory->delivered([], ['key_id' => 'somebody-elses-key']);

        $this->reject(PackageRejected::ANCHOR_UNKNOWN, function () use ($delivered): void {
            $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);
        });
    }

    public function testAnAlgorithmOutsideTheAllowlistIsRefused(): void
    {
        $delivered = $this->factory->delivered([], ['signature_algorithm' => 'rsa-pss']);

        $this->reject(PackageRejected::ALGORITHM_UNSUPPORTED, function () use ($delivered): void {
            $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);
        });
    }

    public function testThePinnedProductionKeyDoesNotVerifyAForeignSignature(): void
    {
        // No signing key for the production anchor exists here, which is the
        // point: a package signed by anything else must not open under it.
        $delivered = $this->factory->delivered([], ['key_id' => 'vtone-2026a']);

        $this->reject(PackageRejected::ENVELOPE_SIGNATURE_INVALID, function () use ($delivered): void {
            $this->opener(new TrustAnchors())->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);
        });
    }

    // ── product and status ───────────────────────────────────────────────

    public function testAnotherSchemaVersionIsRefused(): void
    {
        $this->rejectDelivery(PackageRejected::SCHEMA_MISMATCH, ['schema_version' => 3]);
    }

    public function testAnotherProductIsRefused(): void
    {
        $this->rejectDelivery(PackageRejected::PRODUCT_MISMATCH, ['project' => 'Guardian']);
        $this->rejectDelivery(PackageRejected::PRODUCT_MISMATCH, ['project_slug' => 'guardian']);
    }

    public function testTheEnvelopeAndTheRecordMustDescribeTheSameVersion(): void
    {
        $bytes = $this->factory->recordBytes(['license_version' => 8]);
        $envelope = $this->factory->envelope($bytes, ['license_version' => 7]);

        $this->reject(PackageRejected::PRODUCT_MISMATCH, function () use ($envelope, $bytes): void {
            $this->opener()->open($envelope, base64_encode($bytes), $this->host(), PackageFactory::NOW);
        });
    }

    public function testARecordNotMarkedValidIsRefused(): void
    {
        $this->rejectDelivery(PackageRejected::STATUS_NOT_VALID, ['validation_status' => 'revoked']);
    }

    // ── tier and term ────────────────────────────────────────────────────

    public function testOnlyTheNoChargeTierIsAccepted(): void
    {
        foreach (['pro', 'trial', 'business', ''] as $tier) {
            $this->rejectDelivery(PackageRejected::TIER_NOT_PERMITTED, ['license_package' => $tier]);
        }
    }

    public function testADatedRecordIsRefusedEvenWhenGenuine(): void
    {
        $this->rejectDelivery(PackageRejected::TERM_NOT_PERMITTED, [
            'license_lifetime' => false,
            'license_expires_at' => PackageFactory::NOW + 86400,
        ]);
    }

    public function testAPerpetualRecordMayNotAlsoCarryAnEndDate(): void
    {
        $this->rejectDelivery(PackageRejected::TERM_NOT_PERMITTED, [
            'license_lifetime' => true,
            'license_expires_at' => PackageFactory::NOW + 86400,
        ]);
    }

    public function testADatedRecordWithoutAnEndDateIsRefused(): void
    {
        $this->rejectDelivery(PackageRejected::DATES_INVALID, [
            'license_lifetime' => false,
            'license_expires_at' => null,
        ]);
    }

    public function testAMissingEndDateMemberIsRefusedRatherThanAssumed(): void
    {
        $this->rejectDelivery(PackageRejected::DATES_INVALID, [], [], ['license_expires_at']);
    }

    public function testARecordThatHasNotStartedYetIsRefused(): void
    {
        $this->rejectDelivery(PackageRejected::DATES_INVALID, [
            'license_starts_at' => PackageFactory::NOW + 3600,
        ]);
    }

    // ── the signed host set ──────────────────────────────────────────────

    public function testTheHostSetMustArriveSortedUniqueAndCanonical(): void
    {
        foreach ([
            ['www.example.com', 'example.com'],          // unsorted
            ['example.com', 'example.com'],              // duplicated
            ['example.com', '*.example.com'],            // wildcard
            ['example.com', 'WWW.Example.com'],          // not canonical
            ['example.com', 'example.com.'],             // not canonical
            [],                                          // empty
        ] as $hosts) {
            $this->rejectDelivery(PackageRejected::HOST_SET_INVALID, [
                'license_domains' => $hosts,
                'license_domain' => 'example.com',
            ]);
        }
    }

    public function testTheNamedHostMustBelongToTheSignedSet(): void
    {
        $this->rejectDelivery(PackageRejected::HOST_MISMATCH, [
            'license_domain' => 'shop.example.com',
            'license_domains' => ['example.com', 'www.example.com'],
        ]);
    }

    public function testTheNamedHostMustBeTheHostTheOperationWasFor(): void
    {
        $delivered = $this->factory->delivered();

        // Genuine package for example.com, opened while acting for www.
        $this->reject(PackageRejected::HOST_MISMATCH, function () use ($delivered): void {
            $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host('www.example.com'), PackageFactory::NOW);
        });
    }

    public function testTheAllowanceMustBeAPositiveNumber(): void
    {
        foreach ([0, -1] as $allowance) {
            $this->rejectDelivery(PackageRejected::HOST_SET_INVALID, ['license_max_domains' => $allowance]);
        }
    }

    public function testAnInstanceWideAllowanceIsNotAWildcard(): void
    {
        // 9999 is what the issuer reports for an instance-bound product. It
        // still authorises nothing outside the signed set.
        $delivered = $this->factory->delivered([
            'license_max_domains' => 9999,
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
        ]);

        $package = $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);

        self::assertSame(9999, $package->allowance());
        self::assertCount(1, $package->hosts());
        self::assertFalse(HostName::contains($package->hosts(), $this->host('anything.example.com')));
    }

    public function testMoreBoundHostsThanTheAllowanceStaysValid(): void
    {
        // Lowering an allowance must not take already bound installations dark.
        $delivered = $this->factory->delivered([
            'license_max_domains' => 1,
            'license_domain' => 'example.com',
            'license_domains' => ['example.com', 'www.example.com'],
        ]);

        $package = $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);

        self::assertSame(1, $package->allowance());
        self::assertCount(2, $package->hosts());
    }

    // ── payload shape ────────────────────────────────────────────────────

    public function testAnUnreadablePayloadIsRefused(): void
    {
        $delivered = $this->factory->delivered();

        foreach (['', 'not base64!!', 'YWJj='] as $payload) {
            $this->reject(PackageRejected::PAYLOAD_UNREADABLE, function () use ($delivered, $payload): void {
                $this->opener()->open($delivered['envelope'], $payload, $this->host(), PackageFactory::NOW);
            });
        }
    }

    public function testAnEnvelopeMissingItsMembersIsRefused(): void
    {
        $bytes = $this->factory->recordBytes();

        foreach (['license_md5', 'key_id', 'signature_algorithm', 'license_version', 'generated_at'] as $member) {
            $envelope = $this->factory->envelope($bytes, [], [$member]);

            $this->reject(PackageRejected::ENVELOPE_MALFORMED, function () use ($envelope, $bytes): void {
                $this->opener()->open($envelope, base64_encode($bytes), $this->host(), PackageFactory::NOW);
            }, $member);
        }
    }

    public function testAChecksumThatIsNotAChecksumIsRefused(): void
    {
        $bytes = $this->factory->recordBytes();
        $envelope = $this->factory->envelope($bytes, ['license_md5' => 'nope']);

        $this->reject(PackageRejected::ENVELOPE_MALFORMED, function () use ($envelope, $bytes): void {
            $this->opener()->open($envelope, base64_encode($bytes), $this->host(), PackageFactory::NOW);
        });
    }

    public function testRecordBytesThatAreNotJsonAreRefused(): void
    {
        $bytes = 'this is not a record';
        $envelope = $this->factory->envelope($bytes);

        $this->reject(PackageRejected::RECORD_MALFORMED, function () use ($envelope, $bytes): void {
            $this->opener()->open($envelope, base64_encode($bytes), $this->host(), PackageFactory::NOW);
        });
    }

    public function testTheSignedFormExcludesTheRecordSignature(): void
    {
        // Sanity check on the fixture itself: the signature is computed over the
        // document without it, which is what the issuer does.
        $document = CanonicalJson::decodeObject($this->factory->recordBytes());
        $signature = $document->signature;

        self::assertTrue(DetachedSignature::verify(
            'ed25519',
            CanonicalJson::signedForm($document),
            $signature,
            $this->factory->publicKey,
        ));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function opener(?TrustAnchors $anchors = null): PackageOpener
    {
        return new PackageOpener($anchors ?? $this->factory->anchors(), new ProductProfile());
    }

    private function host(string $host = PackageFactory::HOST): HostName
    {
        $resolved = HostName::tryFrom($host);
        self::assertNotNull($resolved);

        return $resolved;
    }

    private function rejectDelivery(string $category, array $recordOverrides, array $envelopeOverrides = [], array $recordRemove = []): void
    {
        $delivered = $this->factory->delivered($recordOverrides, $envelopeOverrides, $recordRemove);

        $this->reject($category, function () use ($delivered): void {
            $this->opener()->open($delivered['envelope'], $delivered['payload_b64'], $this->host(), PackageFactory::NOW);
        }, json_encode($recordOverrides) ?: '');
    }

    private function reject(string $category, callable $attempt, string $context = ''): void
    {
        try {
            $attempt();
            self::fail('Expected the package to be refused as "' . $category . '". ' . $context);
        } catch (PackageRejected $rejected) {
            self::assertSame($category, $rejected->category(), $context);
        }
    }
}
