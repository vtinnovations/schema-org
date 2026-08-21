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

namespace VTinnovations\SchemaOrg\Storage;

use VTinnovations\SchemaOrg\Config\Paths;

/**
 * Records which inbound pushes have already been applied.
 *
 * A byte-identical retry is reported as already applied; the same request
 * identifier with different content is refused, as is a nonce seen before under
 * another identifier. Only digests are stored, never the nonce itself.
 */
final class ExchangeJournal
{
    public const FRESH = 'fresh';
    public const REPLAY = 'replay';
    public const CONFLICT = 'conflict';

    /** Kept well beyond any sensible retry window. */
    private const NONCE_TTL = 7 * 86400;
    private const REQUEST_TTL = 30 * 86400;

    private const MAX_REQUESTS = 5000;

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * Classify a request against what has already been applied.
     *
     * @return array{outcome: string, version: int}
     */
    public function classify(string $requestId, string $nonce, string $bodyHash, int $now): array
    {
        $journal = $this->read($now);
        $entry = $journal['requests'][$requestId] ?? null;
        $nonceDigest = hash('sha256', $nonce);

        if (\is_array($entry)) {
            $sameBody = hash_equals((string) ($entry['body'] ?? ''), $bodyHash);
            $sameNonce = hash_equals((string) ($entry['nonce'] ?? ''), $nonceDigest);

            if ($sameBody && $sameNonce) {
                return ['outcome' => self::REPLAY, 'version' => (int) ($entry['version'] ?? 0)];
            }

            return ['outcome' => self::CONFLICT, 'version' => 0];
        }

        if (isset($journal['nonces'][$nonceDigest])) {
            return ['outcome' => self::CONFLICT, 'version' => 0];
        }

        return ['outcome' => self::FRESH, 'version' => 0];
    }

    /**
     * Record a request as applied. Called only after the new state is live.
     */
    public function remember(string $requestId, string $nonce, string $bodyHash, int $version, int $now): void
    {
        $this->mutate(function (array $journal) use ($requestId, $nonce, $bodyHash, $version, $now): array {
            $journal['requests'][$requestId] = [
                'body' => $bodyHash,
                'nonce' => hash('sha256', $nonce),
                'version' => $version,
                'at' => $now,
            ];

            $journal['nonces'][hash('sha256', $nonce)] = $now;

            return $journal;
        }, $now);
    }

    public function prune(int $now): void
    {
        $this->mutate(static fn (array $journal): array => $journal, $now);
    }

    /**
     * @return array{requests: array<string, array<string, mixed>>, nonces: array<string, int>}
     */
    private function read(int $now): array
    {
        $raw = @file_get_contents($this->paths->journalFile());
        $data = \is_string($raw) ? json_decode($raw, true) : null;

        return $this->expire(\is_array($data) ? $data : [], $now);
    }

    /**
     * @param callable(array{requests: array<string, array<string, mixed>>, nonces: array<string, int>}): array $change
     */
    private function mutate(callable $change, int $now): void
    {
        $file = $this->paths->journalFile();
        $handle = @fopen($file, 'c+');

        if (false === $handle) {
            throw new \RuntimeException('Could not open the exchange journal.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Could not lock the exchange journal.');
            }

            $size = (int) (fstat($handle)['size'] ?? 0);
            $raw = $size > 0 ? (string) fread($handle, $size) : '';
            $data = json_decode($raw, true);

            $journal = $change($this->expire(\is_array($data) ? $data : [], $now));

            $encoded = json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);

            if (\function_exists('fsync')) {
                @fsync($handle);
            }

            @chmod($file, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{requests: array<string, array<string, mixed>>, nonces: array<string, int>}
     */
    private function expire(array $data, int $now): array
    {
        $requests = \is_array($data['requests'] ?? null) ? $data['requests'] : [];
        $nonces = \is_array($data['nonces'] ?? null) ? $data['nonces'] : [];

        foreach ($requests as $id => $entry) {
            if (!\is_array($entry) || (int) ($entry['at'] ?? 0) + self::REQUEST_TTL < $now) {
                unset($requests[$id]);
            }
        }

        foreach ($nonces as $digest => $seenAt) {
            if ((int) $seenAt + self::NONCE_TTL < $now) {
                unset($nonces[$digest]);
            }
        }

        if (\count($requests) > self::MAX_REQUESTS) {
            uasort($requests, static fn (array $a, array $b): int => (int) ($b['at'] ?? 0) <=> (int) ($a['at'] ?? 0));
            $requests = \array_slice($requests, 0, self::MAX_REQUESTS, true);
        }

        return ['requests' => $requests, 'nonces' => $nonces];
    }
}
