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

namespace VTinnovations\SchemaOrg\Config;

use VTinnovations\SchemaOrg\Serializer\DetachedSignature;

/**
 * The pinned public verification material.
 *
 * These are public keys, not secrets; masking them only removes the grep shortcut.
 * What matters is the pinning: material is reconstructed at runtime and checked
 * against a recorded digest, so a swapped literal is refused. This distribution
 * contains no signing key.
 *
 * An identifier only selects a key. An unknown identifier, a structurally invalid
 * key, one outside its window or one paired with an unapproved algorithm yields
 * nothing, and callers fail closed.
 */
final class TrustAnchors
{
    public const USE_RECORD = 'record';
    public const USE_ENVELOPE = 'envelope';
    public const USE_REQUEST = 'request';

    private const MASK_SEED = 'vtinnovations/schema-org#anchor';

    /**
     * Masked material: hex(raw XOR mask), split, plus the leading 16 hex digits
     * of the SHA-256 over the reconstructed raw key.
     */
    private const PINNED = [
        'vtone-2026a' => [
            'alg' => DetachedSignature::ED25519,
            'a' => '302c584d3f1e163ddd09b114f5dcd370',
            'b' => '099f7c00f983e26fc980fad4af22fb45',
            'digest' => 'edcd614e70c59ce0',
            'from' => 0,
            'until' => null,
            'uses' => [self::USE_RECORD, self::USE_ENVELOPE, self::USE_REQUEST],
        ],
    ];

    /** @var array<string, array{alg: string, key: string, from: int, until: int|null, uses: list<string>}> */
    private readonly array $anchors;

    /**
     * @param array<string, array{alg: string, key: string, from?: int, until?: int|null, uses?: list<string>}>|null $replacement
     *        Test-only substitute. Production always uses the pinned set, which
     *        must never be empty — an empty production set is a build defect and
     *        throws here rather than degrading into an unsigned mode.
     */
    public function __construct(?array $replacement = null)
    {
        $this->anchors = null === $replacement
            ? $this->pinned()
            : $this->validated($replacement);
    }

    public function isEmpty(): bool
    {
        return [] === $this->anchors;
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys($this->anchors);
    }

    /**
     * Raw public key for an exact identifier, or null when it cannot be used for
     * this algorithm/purpose/instant.
     */
    public function resolve(string $identifier, string $algorithm, string $use, int $now): ?string
    {
        $anchor = $this->anchors[$identifier] ?? null;

        if (null === $anchor
            || $anchor['alg'] !== $algorithm
            || !\in_array($use, $anchor['uses'], true)
            || !$this->withinWindow($anchor, $now)
        ) {
            return null;
        }

        return $anchor['key'];
    }

    /**
     * Every currently usable key for a purpose. The record document names no
     * key, so its signature is tried against each of these in turn.
     *
     * @return list<array{id: string, alg: string, key: string}>
     */
    public function usableFor(string $use, int $now): array
    {
        $usable = [];

        foreach ($this->anchors as $identifier => $anchor) {
            if (\in_array($use, $anchor['uses'], true) && $this->withinWindow($anchor, $now)) {
                $usable[] = ['id' => $identifier, 'alg' => $anchor['alg'], 'key' => $anchor['key']];
            }
        }

        return $usable;
    }

    /**
     * @param array{from: int, until: int|null} $anchor
     */
    private function withinWindow(array $anchor, int $now): bool
    {
        if ($now < $anchor['from']) {
            return false;
        }

        return null === $anchor['until'] || $now <= $anchor['until'];
    }

    /**
     * @return array<string, array{alg: string, key: string, from: int, until: int|null, uses: list<string>}>
     */
    private function pinned(): array
    {
        $mask = substr(hash('sha256', self::MASK_SEED, true), 0, DetachedSignature::PUBLIC_KEY_BYTES);
        $resolved = [];

        foreach (self::PINNED as $identifier => $anchor) {
            $masked = hex2bin($anchor['a'] . $anchor['b']);

            if (!\is_string($masked) || \strlen($masked) !== DetachedSignature::PUBLIC_KEY_BYTES) {
                throw new \LogicException('Pinned verification material is malformed.');
            }

            $key = $masked ^ $mask;

            if (!hash_equals($anchor['digest'], substr(hash('sha256', $key), 0, \strlen($anchor['digest'])))) {
                throw new \LogicException('Pinned verification material failed its digest check.');
            }

            $resolved[$identifier] = [
                'alg' => $anchor['alg'],
                'key' => $key,
                'from' => $anchor['from'],
                'until' => $anchor['until'],
                'uses' => $anchor['uses'],
            ];
        }

        if ([] === $resolved) {
            throw new \LogicException('No verification material is pinned in this build.');
        }

        return $resolved;
    }

    /**
     * @param array<string, array{alg: string, key: string, from?: int, until?: int|null, uses?: list<string>}> $replacement
     *
     * @return array<string, array{alg: string, key: string, from: int, until: int|null, uses: list<string>}>
     */
    private function validated(array $replacement): array
    {
        $resolved = [];

        foreach ($replacement as $identifier => $anchor) {
            if ($identifier === ''
                || !\in_array($anchor['alg'] ?? '', [DetachedSignature::ED25519], true)
                || \strlen($anchor['key'] ?? '') !== DetachedSignature::PUBLIC_KEY_BYTES
            ) {
                throw new \InvalidArgumentException('Rejected an unusable verification anchor.');
            }

            $resolved[$identifier] = [
                'alg' => $anchor['alg'],
                'key' => $anchor['key'],
                'from' => (int) ($anchor['from'] ?? 0),
                'until' => isset($anchor['until']) ? (int) $anchor['until'] : null,
                'uses' => $anchor['uses'] ?? [self::USE_RECORD, self::USE_ENVELOPE, self::USE_REQUEST],
            ];
        }

        return $resolved;
    }
}
