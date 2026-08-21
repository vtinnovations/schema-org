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

namespace VTinnovations\SchemaOrg\Operation;

use Psr\Log\LoggerInterface;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\RequestAuthorization;
use VTinnovations\SchemaOrg\Serializer\CanonicalJson;
use VTinnovations\SchemaOrg\Site\DomainInventory;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\ExchangeJournal;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * Applies a package pushed by the issuer.
 *
 * Requires a request signed by a pinned key, header metadata matching the body and
 * a package newer than the stored one. Runs under the state lock, so concurrent
 * pushes are serialised and the second is recognised as already applied.
 */
final class PushIntake
{
    public const APPLIED = 'updated';
    public const ALREADY_APPLIED = 'already_processed';

    public function __construct(
        private readonly RequestAuthorization $authorization,
        private readonly PackageOpener $opener,
        private readonly RecordStore $store,
        private readonly ExchangeJournal $journal,
        private readonly StatusEvaluator $evaluator,
        private readonly DomainInventory $inventory,
        private readonly ProductProfile $profile,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: string, request_id: string, license_version: int}
     *
     * @throws PackageRejected
     */
    public function handle(string $method, string $path, string $rawBody, array $headers, int $now): array
    {
        $meta = $this->authorization->authorize($method, $path, $rawBody, $headers, $now);

        try {
            $body = CanonicalJson::decodeObject($rawBody);
        } catch (\JsonException) {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        $this->assertBodyAgrees($body, $meta);

        $host = HostName::tryFrom((string) ($body->domain ?? ''));

        if (null === $host || !HostName::contains($this->inventory->configuredHosts(), $host)) {
            throw new PackageRejected(PackageRejected::HOST_MISMATCH);
        }

        $envelope = $body->integrity ?? null;
        $payload = $body->license_payload_b64 ?? null;

        if (!$envelope instanceof \stdClass || !\is_string($payload) || $payload === '') {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        /** @var array{status: string, request_id: string, license_version: int} $result */
        $result = $this->store->withLock(function () use ($meta, $envelope, $payload, $host, $now): array {
            $seen = $this->journal->classify($meta['request_id'], $meta['nonce'], $meta['body_hash'], $now);

            if (ExchangeJournal::REPLAY === $seen['outcome']) {
                $this->note($meta['request_id'], self::ALREADY_APPLIED, $seen['version']);

                return [
                    'status' => self::ALREADY_APPLIED,
                    'request_id' => $meta['request_id'],
                    'license_version' => $seen['version'],
                ];
            }

            if (ExchangeJournal::CONFLICT === $seen['outcome']) {
                throw new PackageRejected(PackageRejected::REQUEST_CONFLICT);
            }

            $candidate = $this->opener->open($envelope, $payload, $host, $now);

            $this->assertMovesForward($candidate->key(), $candidate->version(), $now);

            $judged = $this->evaluator->judge($candidate, $now);

            if (!$judged->isEntitled()) {
                throw new PackageRejected($judged->category);
            }

            try {
                $this->store->commit($candidate, $now);
            } catch (PackageRejected $rejected) {
                throw $rejected;
            } catch (\Throwable) {
                throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
            }

            $this->journal->remember($meta['request_id'], $meta['nonce'], $meta['body_hash'], $candidate->version(), $now);
            $this->store->writeSidecar(['category' => 'push_applied']);
            $this->evaluator->forget();
            $this->note($meta['request_id'], self::APPLIED, $candidate->version());

            return [
                'status' => self::APPLIED,
                'request_id' => $meta['request_id'],
                'license_version' => $candidate->version(),
            ];
        });

        return $result;
    }

    /**
     * The metadata appears twice, in the signed headers and in the body. Only
     * the header copy is signed, so the body copy has to agree with it or the
     * request is not the one that was signed.
     *
     * @param array{request_id: string, timestamp: int, nonce: string} $meta
     *
     * @throws PackageRejected
     */
    private function assertBodyAgrees(\stdClass $body, array $meta): void
    {
        if (($body->action ?? null) !== 'license_update') {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        if (($body->project ?? null) !== $this->profile->name()
            || ($body->project_slug ?? null) !== $this->profile->slug()
            || ($body->product_id ?? null) !== $this->profile->catalogueId()
        ) {
            throw new PackageRejected(PackageRejected::PRODUCT_MISMATCH);
        }

        $requestId = $body->request_id ?? null;
        $nonce = $body->nonce ?? null;
        $timestamp = $body->timestamp ?? null;

        if (!\is_string($requestId) || !hash_equals($meta['request_id'], $requestId)
            || !\is_string($nonce) || !hash_equals($meta['nonce'], $nonce)
            || !\is_int($timestamp) || $timestamp !== $meta['timestamp']
        ) {
            throw new PackageRejected(PackageRejected::REQUEST_CONFLICT);
        }
    }

    /**
     * A push must carry something newer than what is stored. Re-sending an
     * older package, or the same version under a new request identifier, is
     * refused rather than applied.
     *
     * @throws PackageRejected
     */
    private function assertMovesForward(string $key, int $version, int $now): void
    {
        $pair = $this->store->readPair();

        if (null === $pair) {
            return;
        }

        try {
            $stored = $this->opener->reopen($pair['envelope'], $pair['bytes'], $now);
        } catch (\Throwable) {
            // Unreadable stored state may always be replaced by a verified one.
            return;
        }

        if (hash_equals($stored->key(), $key) && $version <= $stored->version()) {
            throw new PackageRejected(PackageRejected::VERSION_ROLLBACK);
        }
    }

    private function note(string $requestId, string $result, int $version): void
    {
        $this->logger->info('schema-org package push', [
            'operation' => 'push',
            'request_id' => $requestId,
            'result' => $result,
            'license_version' => $version,
        ]);
    }
}
