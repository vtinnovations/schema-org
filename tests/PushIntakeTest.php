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
use VTinnovations\SchemaOrg\Intake\RequestAuthorization;
use VTinnovations\SchemaOrg\Operation\PushIntake;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\ExchangeJournal;
use VTinnovations\SchemaOrg\Storage\RecordStore;
use VTinnovations\SchemaOrg\Tests\Support\FakeInventory;
use VTinnovations\SchemaOrg\Tests\Support\PackageFactory;
use VTinnovations\SchemaOrg\Tests\Support\RecordingLogger;
use VTinnovations\SchemaOrg\Tests\Support\TempPaths;

/**
 * The issuer pushing a replacement package to this installation.
 */
final class PushIntakeTest extends TestCase
{
    private const PATH = '/rest/api/v1/schema-org-license-updater';

    private PackageFactory $factory;

    private TempPaths $temp;

    private PackageOpener $opener;

    private RecordStore $store;

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
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        $this->temp->destroy();
    }

    public function testASignedPushIsApplied(): void
    {
        $push = $this->push(['license_version' => 9]);
        $result = $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW);

        self::assertSame(PushIntake::APPLIED, $result['status']);
        self::assertSame(9, $result['license_version']);
        self::assertSame(9, $this->storedVersion());
    }

    public function testAnIdenticalRetryIsReportedAsAlreadyApplied(): void
    {
        $push = $this->push(['license_version' => 9]);
        $intake = $this->intake();

        $intake->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW);
        $again = $intake->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW + 3);

        self::assertSame(PushIntake::ALREADY_APPLIED, $again['status']);
        self::assertSame(9, $again['license_version']);
        self::assertSame(9, $this->storedVersion(), 'the second call must not apply anything again');
    }

    public function testTheSameIdentifierWithDifferentContentIsRefused(): void
    {
        $first = $this->push(['license_version' => 9]);
        $intake = $this->intake();
        $intake->handle('POST', self::PATH, $first['body'], $first['headers'], PackageFactory::NOW);

        $second = $this->push(['license_version' => 10], ['request_id' => 'req-1', 'nonce' => 'nonce-2']);

        $this->reject(PackageRejected::REQUEST_CONFLICT, fn () => $intake->handle('POST', self::PATH, $second['body'], $second['headers'], PackageFactory::NOW));
        self::assertSame(9, $this->storedVersion());
    }

    public function testAReusedNonceIsRefused(): void
    {
        $first = $this->push(['license_version' => 9]);
        $intake = $this->intake();
        $intake->handle('POST', self::PATH, $first['body'], $first['headers'], PackageFactory::NOW);

        $second = $this->push(['license_version' => 10], ['request_id' => 'req-2', 'nonce' => 'nonce-1']);

        $this->reject(PackageRejected::REQUEST_CONFLICT, fn () => $intake->handle('POST', self::PATH, $second['body'], $second['headers'], PackageFactory::NOW));
    }

    public function testAnOlderPackageCannotBePushedOverANewerOne(): void
    {
        $first = $this->push(['license_version' => 9]);
        $intake = $this->intake();
        $intake->handle('POST', self::PATH, $first['body'], $first['headers'], PackageFactory::NOW);

        $older = $this->push(['license_version' => 8], ['request_id' => 'req-3', 'nonce' => 'nonce-3']);

        $this->reject(PackageRejected::VERSION_ROLLBACK, fn () => $intake->handle('POST', self::PATH, $older['body'], $older['headers'], PackageFactory::NOW));
        self::assertSame(9, $this->storedVersion());
    }

    public function testTheSameVersionCannotBeReappliedUnderANewIdentifier(): void
    {
        $first = $this->push(['license_version' => 9]);
        $intake = $this->intake();
        $intake->handle('POST', self::PATH, $first['body'], $first['headers'], PackageFactory::NOW);

        $same = $this->push(['license_version' => 9], ['request_id' => 'req-4', 'nonce' => 'nonce-4']);

        $this->reject(PackageRejected::VERSION_ROLLBACK, fn () => $intake->handle('POST', self::PATH, $same['body'], $same['headers'], PackageFactory::NOW));
    }

    public function testAnUnsignedRequestIsRefused(): void
    {
        $push = $this->push();
        $push['headers'][RequestAuthorization::HEADER_SIGNATURE] = base64_encode(str_repeat("\0", 64));

        $this->reject(
            PackageRejected::REQUEST_UNAUTHENTICATED,
            fn () => $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW),
        );

        self::assertFalse($this->store->hasPair());
    }

    public function testTheBodyMustAgreeWithTheSignedHeaders(): void
    {
        // The headers are signed; the body copy of the metadata is not, so it
        // has to match or the request is not the one that was signed.
        $push = $this->push([], [], ['request_id' => 'something-else']);

        $this->reject(
            PackageRejected::REQUEST_CONFLICT,
            fn () => $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW),
        );
    }

    public function testAPushForAnotherProductIsRefused(): void
    {
        $push = $this->push([], [], ['product_id' => 'vt-guardian']);

        $this->reject(
            PackageRejected::PRODUCT_MISMATCH,
            fn () => $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW),
        );
    }

    public function testAPushForAHostThisInstallationDoesNotServeIsRefused(): void
    {
        $push = $this->push();

        $this->reject(
            PackageRejected::HOST_MISMATCH,
            fn () => $this->intake(new FakeInventory(['other.test']))->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW),
        );
    }

    public function testAWrongActionIsRefused(): void
    {
        $push = $this->push([], [], ['action' => 'license_delete']);

        $this->reject(
            PackageRejected::REQUEST_MALFORMED,
            fn () => $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW),
        );
    }

    public function testNothingSensitiveIsLogged(): void
    {
        $push = $this->push(['license_version' => 9]);
        $this->intake()->handle('POST', self::PATH, $push['body'], $push['headers'], PackageFactory::NOW);

        $logged = $this->logger->flatten();

        self::assertStringNotContainsString(PackageFactory::LICENCE_KEY, $logged);
        self::assertStringNotContainsString('nonce-1', $logged);
        self::assertStringNotContainsString('license_payload_b64', $logged);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function intake(?FakeInventory $inventory = null): PushIntake
    {
        $inventory ??= new FakeInventory(['example.com']);

        return new PushIntake(
            new RequestAuthorization($this->factory->anchors()),
            $this->opener,
            $this->store,
            new ExchangeJournal($this->temp->paths),
            new StatusEvaluator($this->store, $this->opener, $inventory),
            $inventory,
            new ProductProfile(),
            $this->logger,
        );
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    private function push(array $recordOverrides = [], array $metaOverrides = [], array $bodyOverrides = []): array
    {
        $delivered = $this->factory->delivered($recordOverrides);

        $requestId = $metaOverrides['request_id'] ?? 'req-1';
        $nonce = $metaOverrides['nonce'] ?? 'nonce-1';
        $timestamp = (int) ($metaOverrides['timestamp'] ?? PackageFactory::NOW);

        $body = json_encode(array_merge([
            'action' => 'license_update',
            'project' => 'SchemaOrg',
            'project_slug' => 'schema-org',
            'product_id' => 'vt-schema-org',
            'domain' => PackageFactory::HOST,
            'request_id' => $requestId,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'license_payload_b64' => $delivered['payload_b64'],
            'integrity' => $delivered['envelope'],
        ], $bodyOverrides), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $message = RequestAuthorization::message('POST', self::PATH, $requestId, (string) $timestamp, $nonce, hash('sha256', $body));

        return [
            'body' => $body,
            'headers' => [
                RequestAuthorization::HEADER_REQUEST_ID => $requestId,
                RequestAuthorization::HEADER_TIMESTAMP => (string) $timestamp,
                RequestAuthorization::HEADER_NONCE => $nonce,
                RequestAuthorization::HEADER_KEY_ID => PackageFactory::KEY_ID,
                RequestAuthorization::HEADER_SIGNATURE => $this->factory->sign($message),
            ],
        ];
    }

    private function storedVersion(): ?int
    {
        $pair = $this->store->readPair();

        if (null === $pair) {
            return null;
        }

        return $this->opener->reopen($pair['envelope'], $pair['bytes'], PackageFactory::NOW)->version();
    }

    private function reject(string $category, callable $attempt): void
    {
        try {
            $attempt();
            self::fail('Expected the push to be refused as "' . $category . '".');
        } catch (PackageRejected $rejected) {
            self::assertSame($category, $rejected->category());
        }
    }
}
