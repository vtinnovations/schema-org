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

namespace VTinnovations\SchemaOrg\Serializer;

/**
 * Detached signature check with an explicit algorithm allowlist.
 *
 * Everything fails closed: an unknown algorithm, a runtime without libsodium, a
 * key of the wrong length or a signature that cannot be decoded all return
 * false rather than skipping the check.
 */
final class DetachedSignature
{
    public const ED25519 = 'ed25519';

    /** Raw Ed25519 sizes, spelled out so the class loads without ext-sodium. */
    public const PUBLIC_KEY_BYTES = 32;
    public const SIGNATURE_BYTES = 64;

    private const ALLOWED = [self::ED25519];

    public static function runtimeReady(): bool
    {
        return \function_exists('sodium_crypto_sign_verify_detached');
    }

    public static function supports(string $algorithm): bool
    {
        return \in_array($algorithm, self::ALLOWED, true) && self::runtimeReady();
    }

    /**
     * Accepts the two unambiguous encodings for a 64 byte detached signature:
     * lowercase hex (128 chars) or strict Base64. Anything else is rejected.
     */
    public static function decode(string $encoded): ?string
    {
        $encoded = trim($encoded);

        if ($encoded === '') {
            return null;
        }

        if (1 === preg_match('/^[0-9a-f]{' . (2 * self::SIGNATURE_BYTES) . '}$/', $encoded)) {
            $raw = hex2bin($encoded);

            return \is_string($raw) ? $raw : null;
        }

        $raw = base64_decode($encoded, true);

        return \is_string($raw) && \strlen($raw) === self::SIGNATURE_BYTES ? $raw : null;
    }

    public static function verify(string $algorithm, string $message, string $signature, string $publicKey): bool
    {
        if (!self::supports($algorithm)) {
            return false;
        }

        if (\strlen($publicKey) !== self::PUBLIC_KEY_BYTES) {
            return false;
        }

        $raw = self::decode($signature);
        if (null === $raw) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($raw, $message, $publicKey);
        } catch (\SodiumException) {
            return false;
        }
    }
}
