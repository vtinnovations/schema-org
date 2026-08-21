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

namespace VTinnovations\SchemaOrg\Remote;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Talks to the fixed exchange address for activation and operator refresh.
 *
 * Only the envelope is returned; nothing here decides whether the answer is
 * trustworthy. Redirects are refused rather than followed — a redirect is a
 * third party choosing the destination — and the response is capped, type
 * checked and correlated before it is parsed.
 *
 * The client runs on Symfony's HTTP client, which uses libcurl when the
 * extension is present. That keeps TLS peer and host verification, timeouts and
 * redirect control in one place instead of hand-rolling a socket.
 */
final class ExchangeClient implements PackageSource
{
    private const CONNECT_TIMEOUT = 5.0;
    private const MAX_DURATION = 15.0;
    private const MAX_RESPONSE_BYTES = 262144;

    /** Tolerated difference between our clock and the issuer's. */
    private const MAX_SKEW = 900;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly EndpointRegistry $endpoints,
        private readonly ProductProfile $profile,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{envelope: \stdClass, payload_b64: string, request_id: string}
     *
     * @throws ExchangeFailure
     */
    public function exchange(string $action, string $key, HostName $host, ?int $currentVersion, int $now): array
    {
        $requestId = bin2hex(random_bytes(16));

        $packet = [
            'action' => $action,
            'project' => $this->profile->name(),
            'project_slug' => $this->profile->slug(),
            'product_id' => $this->profile->catalogueId(),
            'license_key' => $key,
            'domain' => $host->toString(),
            'request_id' => $requestId,
            'timestamp' => $now,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        if (PackageSource::REFRESH === $action && null !== $currentVersion) {
            $packet['current_license_version'] = $currentVersion;
        }

        $url = $this->endpoints->exchangeUrl();
        $startedAt = microtime(true);
        $status = 0;

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'max_redirects' => 0,
                'timeout' => self::CONNECT_TIMEOUT,
                'max_duration' => self::MAX_DURATION,
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            [$status, $body] = $this->readBounded($response);
        } catch (ExchangeFailure $failure) {
            $this->note($action, $requestId, $status, $startedAt, $failure->category());

            throw $failure;
        } catch (\Throwable) {
            // Nothing from the remote side is echoed anywhere: a raw error body
            // or exception text can carry packet material.
            $this->note($action, $requestId, $status, $startedAt, ExchangeFailure::TRANSPORT);

            throw new ExchangeFailure(ExchangeFailure::TRANSPORT, true);
        }

        $answer = $this->interpret($body, $requestId, $now);
        $this->note($action, $requestId, $status, $startedAt, 'accepted');

        return $answer;
    }

    /**
     * @return array{0: int, 1: string}
     *
     * @throws ExchangeFailure
     */
    private function readBounded(ResponseInterface $response): array
    {
        $body = '';
        $status = 0;

        foreach ($this->client->stream($response, self::MAX_DURATION) as $chunk) {
            if ($chunk->isTimeout()) {
                throw new ExchangeFailure(ExchangeFailure::TRANSPORT, true);
            }

            if ($chunk->isFirst()) {
                $status = $response->getStatusCode();

                if ($status >= 500) {
                    throw new ExchangeFailure(ExchangeFailure::SERVER_ERROR, true);
                }

                if (200 !== $status) {
                    throw new ExchangeFailure(ExchangeFailure::DENIED);
                }

                $type = strtolower((string) ($response->getHeaders(false)['content-type'][0] ?? ''));

                if (!str_starts_with($type, 'application/json')) {
                    throw new ExchangeFailure(ExchangeFailure::MEDIA_TYPE);
                }

                continue;
            }

            $body .= $chunk->getContent();

            if (\strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw new ExchangeFailure(ExchangeFailure::TOO_LARGE);
            }

            if ($chunk->isLast()) {
                break;
            }
        }

        return [$status, $body];
    }

    /**
     * @return array{envelope: \stdClass, payload_b64: string, request_id: string}
     *
     * @throws ExchangeFailure
     */
    private function interpret(string $body, string $requestId, int $now): array
    {
        try {
            $data = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ExchangeFailure(ExchangeFailure::MALFORMED);
        }

        if (!$data instanceof \stdClass) {
            throw new ExchangeFailure(ExchangeFailure::MALFORMED);
        }

        $echoed = $data->request_id ?? null;

        if (!\is_string($echoed) || !hash_equals($requestId, $echoed)) {
            throw new ExchangeFailure(ExchangeFailure::CORRELATION);
        }

        $serverTime = $data->server_time ?? null;

        if (!\is_int($serverTime) || abs($serverTime - $now) > self::MAX_SKEW) {
            throw new ExchangeFailure(ExchangeFailure::CLOCK_SKEW);
        }

        if (($data->status ?? null) !== 'valid') {
            throw new ExchangeFailure(ExchangeFailure::DENIED);
        }

        $payload = $data->license_payload_b64 ?? null;
        $envelope = $data->integrity ?? null;

        if (!\is_string($payload) || $payload === '' || !$envelope instanceof \stdClass) {
            throw new ExchangeFailure(ExchangeFailure::MALFORMED);
        }

        return ['envelope' => $envelope, 'payload_b64' => $payload, 'request_id' => $requestId];
    }

    /**
     * Operational metadata only. No packet, no body, no nonce, no key, no key
     * length, no checksum, no signature and no digest of any of them.
     */
    private function note(string $action, string $requestId, int $status, float $startedAt, string $result): void
    {
        $this->logger->info('schema-org package exchange', [
            'operation' => $action,
            'request_id' => $requestId,
            'http_status' => $status,
            'result' => $result,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
