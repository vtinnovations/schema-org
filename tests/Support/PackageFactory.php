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

namespace VTinnovations\SchemaOrg\Tests\Support;

use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Serializer\CanonicalJson;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;

/**
 * Builds signed packages for the suite.
 *
 * The key pair is derived from a fixed seed, so every run produces byte
 * identical fixtures and the signatures below are reproducible vectors rather
 * than something regenerated each time. It is a test key: it is never pinned in
 * the product, and the production anchor is only ever used here in negative
 * assertions, because no signing key for it exists outside the issuer.
 */
final class PackageFactory
{
    public const NOW = 1784900000;
    public const KEY_ID = 'test-2026a';
    public const LICENCE_KEY = 'VT-SCHEMA-ORG-TEST-0001';
    public const HOST = 'example.com';

    public readonly string $publicKey;

    private readonly string $secretKey;

    public function __construct(string $seed = 'vtinnovations/schema-org#test-seed')
    {
        $pair = sodium_crypto_sign_seed_keypair(substr(hash('sha256', $seed, true), 0, SODIUM_CRYPTO_SIGN_SEEDBYTES));

        $this->publicKey = sodium_crypto_sign_publickey($pair);
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
    }

    /**
     * A ring containing only the test key, so verification runs through exactly
     * the production code path with material the suite controls.
     */
    public function anchors(?int $until = null): TrustAnchors
    {
        return new TrustAnchors([
            self::KEY_ID => [
                'alg' => DetachedSignature::ED25519,
                'key' => $this->publicKey,
                'from' => 0,
                'until' => $until,
                'uses' => [TrustAnchors::USE_RECORD, TrustAnchors::USE_ENVELOPE, TrustAnchors::USE_REQUEST],
            ],
        ]);
    }

    public function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));
    }

    /**
     * @return array<string, mixed>
     */
    public function recordArray(array $overrides = [], array $remove = []): array
    {
        $record = [
            'schema_version' => 2,
            'project' => 'SchemaOrg',
            'project_slug' => 'schema-org',
            'license_key' => self::LICENCE_KEY,
            'license_domain' => self::HOST,
            'license_domains' => ['example.com', 'www.example.com'],
            'license_max_domains' => 9999,
            'license_package' => 'free',
            'license_features' => [],
            'license_version' => 7,
            'license_issued_at' => 1784000000,
            'license_starts_at' => 1784000000,
            'license_expires_at' => null,
            'license_lifetime' => true,
            'license_verified_at' => 1784880547,
            'free_available' => true,
            'validation_status' => 'valid',
        ];

        foreach ($remove as $member) {
            unset($record[$member]);
        }

        return array_merge($record, $overrides);
    }

    /**
     * Exact record bytes, signed unless the caller supplied its own signature.
     */
    public function recordBytes(array $overrides = [], array $remove = []): string
    {
        $document = $this->toObject($this->recordArray($overrides, $remove));

        if (!isset($document->signature)) {
            $document->signature = $this->sign(CanonicalJson::signedForm($document));
        }

        return CanonicalJson::encode($document);
    }

    public function envelope(string $bytes, array $overrides = [], array $remove = []): \stdClass
    {
        $envelope = [
            'project' => 'SchemaOrg',
            'project_slug' => 'schema-org',
            'license_version' => 7,
            'license_md5' => md5($bytes),
            'generated_at' => self::NOW,
            'key_id' => self::KEY_ID,
            'signature_algorithm' => DetachedSignature::ED25519,
        ];

        foreach ($remove as $member) {
            unset($envelope[$member]);
        }

        $object = $this->toObject(array_merge($envelope, $overrides));

        if (!isset($object->signature)) {
            $object->signature = $this->sign(CanonicalJson::signedForm($object));
        }

        return $object;
    }

    /**
     * A complete delivery: exact bytes, their Base64 form and the envelope.
     *
     * @return array{bytes: string, payload_b64: string, envelope: \stdClass}
     */
    public function delivered(
        array $recordOverrides = [],
        array $envelopeOverrides = [],
        array $recordRemove = [],
        array $envelopeRemove = [],
    ): array {
        $bytes = $this->recordBytes($recordOverrides, $recordRemove);

        $envelopeOverrides += ['license_version' => $recordOverrides['license_version'] ?? 7];

        return [
            'bytes' => $bytes,
            'payload_b64' => base64_encode($bytes),
            'envelope' => $this->envelope($bytes, $envelopeOverrides, $envelopeRemove),
        ];
    }

    private function toObject(array $data): \stdClass
    {
        /** @var \stdClass $object */
        $object = json_decode(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            false,
            64,
            JSON_THROW_ON_ERROR,
        );

        return $object;
    }
}
