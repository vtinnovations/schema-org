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

namespace VTinnovations\SchemaOrg\Intake;

use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Serializer\CanonicalJson;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;
use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Verifies a delivered package step by step and refuses it at the first check
 * that fails.
 *
 * The envelope is authenticated before its checksum is trusted, and the checksum
 * is compared against the delivered bytes rather than a re-serialised copy. The
 * record carries a separate signature of its own, so a valid envelope alone is not
 * sufficient.
 */
final class PackageOpener
{
    /** Generous ceiling for one record; a real one is well under a kilobyte. */
    public const MAX_RECORD_BYTES = 32768;

    /** Tolerated clock difference when judging "already started". */
    private const START_SKEW = 300;

    public function __construct(
        private readonly TrustAnchors $anchors,
        private readonly ProductProfile $profile,
    ) {
    }

    /**
     * Open a package as delivered by an exchange or a push.
     *
     * @param HostName $operationHost host this operation was performed for; the
     *                                record must name exactly this host
     *
     * @throws PackageRejected
     */
    public function open(\stdClass $envelope, string $payloadB64, HostName $operationHost, int $now): SealedPackage
    {
        return $this->verify($envelope, $this->decodePayload($payloadB64), $operationHost, $now);
    }

    /**
     * Re-open the stored pair. Everything is checked again — a package is never
     * trusted because it was trusted once. The operation host is not re-applied
     * here: it described the moment of issue, while membership in the signed
     * host set is what still has to hold.
     *
     * @throws PackageRejected
     */
    public function reopen(string $envelopeJson, string $bytes, int $now): SealedPackage
    {
        try {
            $envelope = CanonicalJson::decodeObject($envelopeJson);
        } catch (\JsonException) {
            throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
        }

        return $this->verify($envelope, $bytes, null, $now);
    }

    /**
     * @throws PackageRejected
     */
    private function verify(\stdClass $envelope, string $bytes, ?HostName $operationHost, int $now): SealedPackage
    {
        $this->assertEnvelopeShape($envelope);

        $algorithm = (string) $envelope->signature_algorithm;
        if (!DetachedSignature::supports($algorithm)) {
            throw new PackageRejected(PackageRejected::ALGORITHM_UNSUPPORTED);
        }

        if ($this->anchors->isEmpty()) {
            throw new PackageRejected(PackageRejected::ANCHOR_STORE_EMPTY);
        }

        $anchor = $this->anchors->resolve((string) $envelope->key_id, $algorithm, TrustAnchors::USE_ENVELOPE, $now);
        if (null === $anchor) {
            throw new PackageRejected(PackageRejected::ANCHOR_UNKNOWN);
        }

        try {
            $envelopeSignedForm = CanonicalJson::signedForm($envelope);
            $envelopeJson = CanonicalJson::encode($envelope);
        } catch (\JsonException) {
            throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
        }

        if (!DetachedSignature::verify($algorithm, $envelopeSignedForm, (string) $envelope->signature, $anchor)) {
            throw new PackageRejected(PackageRejected::ENVELOPE_SIGNATURE_INVALID);
        }

        // Only now may the checksum in the envelope be believed, and only
        // against the bytes exactly as they arrived.
        if (!hash_equals(strtolower((string) $envelope->license_md5), md5($bytes))) {
            throw new PackageRejected(PackageRejected::CHECKSUM_MISMATCH);
        }

        if ($bytes === '' || \strlen($bytes) > self::MAX_RECORD_BYTES) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        try {
            $record = CanonicalJson::decodeObject($bytes);
        } catch (\JsonException) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        $this->assertRecordSignature($record, $now);
        $this->assertProduct($record, $envelope);

        return $this->assemble($record, $bytes, $envelopeJson, $operationHost, $now);
    }

    /**
     * @throws PackageRejected
     */
    private function assertEnvelopeShape(\stdClass $envelope): void
    {
        $strings = ['project', 'project_slug', 'license_md5', 'key_id', 'signature_algorithm', 'signature'];

        foreach ($strings as $member) {
            if (!isset($envelope->{$member}) || !\is_string($envelope->{$member}) || $envelope->{$member} === '') {
                throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
            }
        }

        if (1 !== preg_match('/^[0-9a-fA-F]{32}$/', $envelope->license_md5)) {
            throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
        }

        if (!isset($envelope->license_version) || !\is_int($envelope->license_version) || $envelope->license_version < 1) {
            throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
        }

        if (!isset($envelope->generated_at) || !\is_int($envelope->generated_at) || $envelope->generated_at < 0) {
            throw new PackageRejected(PackageRejected::ENVELOPE_MALFORMED);
        }
    }

    /**
     * The record names no key, so its detached signature is tried against every
     * anchor currently usable for records.
     *
     * @throws PackageRejected
     */
    private function assertRecordSignature(\stdClass $record, int $now): void
    {
        if (!isset($record->signature) || !\is_string($record->signature) || $record->signature === '') {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        $usable = $this->anchors->usableFor(TrustAnchors::USE_RECORD, $now);
        if ([] === $usable) {
            throw new PackageRejected(PackageRejected::ANCHOR_STORE_EMPTY);
        }

        try {
            $signedForm = CanonicalJson::signedForm($record);
        } catch (\JsonException) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        foreach ($usable as $candidate) {
            if (DetachedSignature::verify($candidate['alg'], $signedForm, $record->signature, $candidate['key'])) {
                return;
            }
        }

        throw new PackageRejected(PackageRejected::RECORD_SIGNATURE_INVALID);
    }

    /**
     * @throws PackageRejected
     */
    private function assertProduct(\stdClass $record, \stdClass $envelope): void
    {
        if (($record->schema_version ?? null) !== ProductProfile::SCHEMA) {
            throw new PackageRejected(PackageRejected::SCHEMA_MISMATCH);
        }

        if (($record->project ?? null) !== $this->profile->name()
            || ($record->project_slug ?? null) !== $this->profile->slug()
        ) {
            throw new PackageRejected(PackageRejected::PRODUCT_MISMATCH);
        }

        // The two signed structures must describe the same thing.
        if ($envelope->project !== $record->project
            || $envelope->project_slug !== $record->project_slug
            || $envelope->license_version !== ($record->license_version ?? null)
        ) {
            throw new PackageRejected(PackageRejected::PRODUCT_MISMATCH);
        }

        if (($record->validation_status ?? null) !== 'valid') {
            throw new PackageRejected(PackageRejected::STATUS_NOT_VALID);
        }
    }

    /**
     * @throws PackageRejected
     */
    private function assemble(\stdClass $record, string $bytes, string $envelopeJson, ?HostName $operationHost, int $now): SealedPackage
    {
        $key = $record->license_key ?? null;
        if (!\is_string($key) || !$this->profile->looksLikeKey($key)) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        $hosts = $this->readHostSet($record);
        $named = HostName::tryFrom((string) ($record->license_domain ?? ''));

        if (null === $named || !HostName::contains($hosts, $named)) {
            throw new PackageRejected(PackageRejected::HOST_MISMATCH);
        }

        // The record must name exactly the host this operation was performed
        // for; equality, never a related or parent name.
        if (null !== $operationHost && !$named->equals($operationHost)) {
            throw new PackageRejected(PackageRejected::HOST_MISMATCH);
        }

        $allowance = $record->license_max_domains ?? null;
        if (!\is_int($allowance) || $allowance < 1) {
            throw new PackageRejected(PackageRejected::HOST_SET_INVALID);
        }

        $tier = $record->license_package ?? null;
        if (!\is_string($tier) || !$this->profile->acceptsTier($tier)) {
            throw new PackageRejected(PackageRejected::TIER_NOT_PERMITTED);
        }

        $features = $this->readFeatures($record);

        $version = $record->license_version ?? null;
        if (!\is_int($version) || $version < 1) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        [$issuedAt, $startsAt, $endsAt, $perpetual, $verifiedAt] = $this->readTerm($record, $now);

        $fallback = $record->free_available ?? null;
        if (!\is_bool($fallback)) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        return new SealedPackage(
            $bytes,
            $envelopeJson,
            $key,
            $named,
            $hosts,
            $allowance,
            $tier,
            $features,
            $version,
            $issuedAt,
            $startsAt,
            $endsAt,
            $perpetual,
            $verifiedAt,
            $fallback,
        );
    }

    /**
     * The signed host set must arrive already canonical: unique, sorted and
     * free of wildcards. It is validated, never repaired — repairing it locally
     * would mean signing over the issuer's decision.
     *
     * @return list<HostName>
     *
     * @throws PackageRejected
     */
    private function readHostSet(\stdClass $record): array
    {
        $raw = $record->license_domains ?? null;

        if (!\is_array($raw) || [] === $raw || array_is_list($raw) === false) {
            throw new PackageRejected(PackageRejected::HOST_SET_INVALID);
        }

        $hosts = [];
        $previous = null;

        foreach ($raw as $entry) {
            if (!\is_string($entry)) {
                throw new PackageRejected(PackageRejected::HOST_SET_INVALID);
            }

            $host = HostName::tryFrom($entry);

            // Already-canonical means the entry survives normalisation unchanged.
            if (null === $host || $host->toString() !== $entry) {
                throw new PackageRejected(PackageRejected::HOST_SET_INVALID);
            }

            if (null !== $previous && strcmp($entry, $previous) <= 0) {
                throw new PackageRejected(PackageRejected::HOST_SET_INVALID);
            }

            $previous = $entry;
            $hosts[] = $host;
        }

        return $hosts;
    }

    /**
     * @return list<string>
     *
     * @throws PackageRejected
     */
    private function readFeatures(\stdClass $record): array
    {
        $raw = $record->license_features ?? null;

        if (!\is_array($raw) || array_is_list($raw) === false) {
            throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
        }

        foreach ($raw as $feature) {
            if (!\is_string($feature)) {
                throw new PackageRejected(PackageRejected::RECORD_MALFORMED);
            }
        }

        return array_values($raw);
    }

    /**
     * @return array{0: int, 1: int, 2: int|null, 3: bool, 4: int}
     *
     * @throws PackageRejected
     */
    private function readTerm(\stdClass $record, int $now): array
    {
        $issuedAt = $record->license_issued_at ?? null;
        $startsAt = $record->license_starts_at ?? null;
        $verifiedAt = $record->license_verified_at ?? null;
        $perpetual = $record->license_lifetime ?? null;
        $endsAt = \array_key_exists('license_expires_at', get_object_vars($record))
            ? $record->license_expires_at
            : false;

        if (!\is_int($issuedAt) || !\is_int($startsAt) || !\is_int($verifiedAt)
            || $issuedAt < 1 || $startsAt < 1 || $verifiedAt < 0
        ) {
            throw new PackageRejected(PackageRejected::DATES_INVALID);
        }

        if (!\is_bool($perpetual)) {
            throw new PackageRejected(PackageRejected::TERM_NOT_PERMITTED);
        }

        if (false === $endsAt) {
            throw new PackageRejected(PackageRejected::DATES_INVALID);
        }

        if ($perpetual) {
            // A perpetual term has no end date at all.
            if (null !== $endsAt) {
                throw new PackageRejected(PackageRejected::TERM_NOT_PERMITTED);
            }
        } else {
            if (!\is_int($endsAt) || $endsAt <= $startsAt) {
                throw new PackageRejected(PackageRejected::DATES_INVALID);
            }
        }

        // This distribution has no dated mode: refuse a dated record outright
        // rather than running it and expiring in some untested state.
        if ($this->profile->requiresPerpetualTerm() && !$perpetual) {
            throw new PackageRejected(PackageRejected::TERM_NOT_PERMITTED);
        }

        if ($startsAt > $now + self::START_SKEW) {
            throw new PackageRejected(PackageRejected::DATES_INVALID);
        }

        return [$issuedAt, $startsAt, \is_int($endsAt) ? $endsAt : null, $perpetual, $verifiedAt];
    }

    /**
     * @throws PackageRejected
     */
    private function decodePayload(string $payloadB64): string
    {
        $payloadB64 = trim($payloadB64);

        if ($payloadB64 === ''
            || \strlen($payloadB64) > 4 * self::MAX_RECORD_BYTES
            || 0 !== \strlen($payloadB64) % 4
            || 1 !== preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $payloadB64)
        ) {
            throw new PackageRejected(PackageRejected::PAYLOAD_UNREADABLE);
        }

        $bytes = base64_decode($payloadB64, true);

        if (!\is_string($bytes) || $bytes === '') {
            throw new PackageRejected(PackageRejected::PAYLOAD_UNREADABLE);
        }

        return $bytes;
    }
}
