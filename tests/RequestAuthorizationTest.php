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
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\RequestAuthorization;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;

/**
 * The push endpoint has no session behind it, so this signature is the only
 * thing standing between the issuer and anyone else who can reach the address.
 */
final class RequestAuthorizationTest extends TestCase
{
    private const PATH = '/rest/api/v1/schema-org-license-updater';

    private PackageFactory $factory;

    protected function setUp(): void
    {
        if (!DetachedSignature::runtimeReady()) {
            self::markTestSkipped('libsodium is not available');
        }

        $this->factory = new PackageFactory();
    }

    public function testTheSignedMessageIsSixLinesInAFixedOrder(): void
    {
        $message = RequestAuthorization::message('post', self::PATH, 'req-1', '1784900000', 'nonce-1', 'AB12');

        self::assertSame(
            "POST\n" . self::PATH . "\nreq-1\n1784900000\nnonce-1\nab12",
            $message,
            'method is uppercased, the body hash lowercased, nothing else is touched',
        );
    }

    public function testAccceptsAProperlySignedRequest(): void
    {
        $body = '{"action":"license_update"}';
        $meta = $this->authorize($body, $this->headers($body));

        self::assertSame('req-1', $meta['request_id']);
        self::assertSame('nonce-1', $meta['nonce']);
        self::assertSame(PackageFactory::KEY_ID, $meta['key_id']);
        self::assertSame(hash('sha256', $body), $meta['body_hash']);
    }

    public function testABodyChangedInFlightIsRefused(): void
    {
        $headers = $this->headers('{"action":"license_update"}');

        $this->reject(PackageRejected::REQUEST_UNAUTHENTICATED, fn () => $this->authorize('{"action":"license_update"} ', $headers));
    }

    public function testAnotherPathWithTheSameSignatureIsRefused(): void
    {
        $body = '{}';
        $headers = $this->headers($body);

        $this->reject(
            PackageRejected::REQUEST_UNAUTHENTICATED,
            fn () => $this->authorize($body, $headers, '/rest/api/v1/guardian-license-updater'),
        );
    }

    public function testAnotherMethodWithTheSameSignatureIsRefused(): void
    {
        $body = '{}';
        $headers = $this->headers($body);

        $this->reject(
            PackageRejected::REQUEST_UNAUTHENTICATED,
            fn () => $this->authorize($body, $headers, self::PATH, 'PUT'),
        );
    }

    public function testAStaleOrFutureTimestampIsRefused(): void
    {
        foreach ([PackageFactory::NOW - 3600, PackageFactory::NOW + 3600] as $timestamp) {
            $body = '{}';
            $headers = $this->headers($body, ['timestamp' => (string) $timestamp]);

            $this->reject(PackageRejected::REQUEST_STALE, fn () => $this->authorize($body, $headers));
        }
    }

    public function testAnUnknownKeyIdentifierIsRefusedBeforeTheSignatureIsEvenTried(): void
    {
        // The identifier only selects a key. Naming a key we do not have pinned
        // cannot make a request acceptable.
        $body = '{}';
        $headers = $this->headers($body);
        $headers[RequestAuthorization::HEADER_KEY_ID] = 'somebody-elses-key';

        $this->reject(PackageRejected::ANCHOR_UNKNOWN, fn () => $this->authorize($body, $headers));
    }

    public function testTheKeyIdentifierIsNotPartOfTheSignedMessage(): void
    {
        // Swapping in a second pinned identifier that maps to the same key
        // still verifies, which is what "selects the key, is not signed" means.
        $body = '{}';
        $headers = $this->headers($body);
        $headers[RequestAuthorization::HEADER_KEY_ID] = 'test-mirror';

        $anchors = new TrustAnchors([
            'test-mirror' => [
                'alg' => 'ed25519',
                'key' => $this->factory->publicKey,
                'uses' => [TrustAnchors::USE_REQUEST],
            ],
        ]);

        $meta = (new RequestAuthorization($anchors))->authorize('POST', self::PATH, $body, $headers, PackageFactory::NOW);

        self::assertSame('test-mirror', $meta['key_id']);
    }

    public function testAnEmptyRingRefusesEveryRequest(): void
    {
        $body = '{}';
        $headers = $this->headers($body);

        $this->reject(
            PackageRejected::ANCHOR_STORE_EMPTY,
            fn () => (new RequestAuthorization(new TrustAnchors([])))->authorize('POST', self::PATH, $body, $headers, PackageFactory::NOW),
        );
    }

    public function testMissingOrMalformedHeadersAreRefused(): void
    {
        $body = '{}';

        foreach ([
            RequestAuthorization::HEADER_REQUEST_ID,
            RequestAuthorization::HEADER_NONCE,
            RequestAuthorization::HEADER_KEY_ID,
            RequestAuthorization::HEADER_SIGNATURE,
            RequestAuthorization::HEADER_TIMESTAMP,
        ] as $header) {
            $headers = $this->headers($body);
            $headers[$header] = '';

            $this->reject(PackageRejected::REQUEST_MALFORMED, fn () => $this->authorize($body, $headers), $header);
        }

        $headers = $this->headers($body, ['timestamp' => '+1784900000']);
        $this->reject(PackageRejected::REQUEST_MALFORMED, fn () => $this->authorize($body, $headers));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function headers(string $body, array $overrides = []): array
    {
        $requestId = $overrides['request_id'] ?? 'req-1';
        $timestamp = $overrides['timestamp'] ?? (string) PackageFactory::NOW;
        $nonce = $overrides['nonce'] ?? 'nonce-1';

        $message = RequestAuthorization::message('POST', self::PATH, $requestId, $timestamp, $nonce, hash('sha256', $body));

        return [
            RequestAuthorization::HEADER_REQUEST_ID => $requestId,
            RequestAuthorization::HEADER_TIMESTAMP => $timestamp,
            RequestAuthorization::HEADER_NONCE => $nonce,
            RequestAuthorization::HEADER_KEY_ID => PackageFactory::KEY_ID,
            RequestAuthorization::HEADER_SIGNATURE => $this->factory->sign($message),
        ];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{request_id: string, timestamp: int, nonce: string, key_id: string, body_hash: string}
     */
    private function authorize(string $body, array $headers, string $path = self::PATH, string $method = 'POST'): array
    {
        return (new RequestAuthorization($this->factory->anchors()))
            ->authorize($method, $path, $body, $headers, PackageFactory::NOW);
    }

    private function reject(string $category, callable $attempt, string $context = ''): void
    {
        try {
            $attempt();
            self::fail('Expected the request to be refused as "' . $category . '". ' . $context);
        } catch (PackageRejected $rejected) {
            self::assertSame($category, $rejected->category(), $context);
        }
    }
}
