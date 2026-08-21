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

use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Serializer\DetachedSignature;

/**
 * Authenticates an inbound push.
 *
 * The request is server to server, so there is no session and no browser token.
 * Origin, Referer, user agent and source address are caller-controlled and are
 * ignored; authentication is a signature over method, path, request metadata and a
 * hash of the raw body, verified against a pinned key.
 *
 * The key identifier header selects the key and is not part of the signed
 * message.
 */
final class RequestAuthorization
{
    public const HEADER_REQUEST_ID = 'X-VT-Request-ID';
    public const HEADER_TIMESTAMP = 'X-VT-Timestamp';
    public const HEADER_NONCE = 'X-VT-Nonce';
    public const HEADER_KEY_ID = 'X-VT-Key-ID';
    public const HEADER_SIGNATURE = 'X-VT-Signature';

    /** Accepted clock difference in either direction. */
    private const WINDOW = 300;

    private const MAX_TOKEN_LENGTH = 190;

    public function __construct(private readonly TrustAnchors $anchors)
    {
    }

    /**
     * @param array<string, string> $headers header name (as above) to value
     *
     * @return array{request_id: string, timestamp: int, nonce: string, key_id: string, body_hash: string}
     *
     * @throws PackageRejected
     */
    public function authorize(string $method, string $path, string $rawBody, array $headers, int $now): array
    {
        $requestId = $this->token($headers, self::HEADER_REQUEST_ID);
        $nonce = $this->token($headers, self::HEADER_NONCE);
        $keyId = $this->token($headers, self::HEADER_KEY_ID);
        $signature = $this->token($headers, self::HEADER_SIGNATURE, 512);
        $timestampRaw = $this->token($headers, self::HEADER_TIMESTAMP, 20);

        if (1 !== preg_match('/^(0|[1-9][0-9]*)$/', $timestampRaw)) {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        $timestamp = (int) $timestampRaw;

        if (abs($now - $timestamp) > self::WINDOW) {
            throw new PackageRejected(PackageRejected::REQUEST_STALE);
        }

        if ($this->anchors->isEmpty()) {
            throw new PackageRejected(PackageRejected::ANCHOR_STORE_EMPTY);
        }

        $anchor = $this->anchors->resolve($keyId, DetachedSignature::ED25519, TrustAnchors::USE_REQUEST, $now);
        if (null === $anchor) {
            throw new PackageRejected(PackageRejected::ANCHOR_UNKNOWN);
        }

        $bodyHash = hash('sha256', $rawBody);
        $message = self::message($method, $path, $requestId, $timestampRaw, $nonce, $bodyHash);

        if (!DetachedSignature::verify(DetachedSignature::ED25519, $message, $signature, $anchor)) {
            throw new PackageRejected(PackageRejected::REQUEST_UNAUTHENTICATED);
        }

        return [
            'request_id' => $requestId,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'key_id' => $keyId,
            'body_hash' => $bodyHash,
        ];
    }

    /**
     * The exact six line message both sides construct, joined with newlines and
     * with no trailing newline.
     */
    public static function message(
        string $method,
        string $path,
        string $requestId,
        string $timestamp,
        string $nonce,
        string $bodyHash,
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $requestId,
            $timestamp,
            $nonce,
            strtolower($bodyHash),
        ]);
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws PackageRejected
     */
    private function token(array $headers, string $name, int $maxLength = self::MAX_TOKEN_LENGTH): string
    {
        $value = trim((string) ($headers[$name] ?? ''));

        if ($value === '' || \strlen($value) > $maxLength) {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        if (1 === preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new PackageRejected(PackageRejected::REQUEST_MALFORMED);
        }

        return $value;
    }
}
