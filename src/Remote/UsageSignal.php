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

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\InstallationStatus;

/**
 * The two server-to-server signals sent to the fixed logging address.
 *
 * They remain separate events: an invocation signal carrying product name and
 * host, at most once per request and never a key; and a section entry signal
 * carrying host and key, at most once per authenticated backend session.
 *
 * The entry signal is the only place a full key leaves the installation outside an
 * exchange. The key comes from a verified record, the claim is made before
 * delivery so a timeout cannot cause a retry, and the marker holds no key, host or
 * session identifier.
 *
 * Both are queued and sent after the response. Failures are silent and never
 * affect entitlement or rendering.
 */
final class UsageSignal
{
    private const CONNECT_TIMEOUT = 2.0;
    private const MAX_DURATION = 5.0;

    /** @var list<array<string, string>> */
    private array $queue = [];

    private bool $invocationQueued = false;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly EndpointRegistry $endpoints,
        private readonly ProductProfile $profile,
    ) {
    }

    /** Exactly the two documented members, and never a key. */
    public function queueInvocation(HostName $host): void
    {
        if ($this->invocationQueued) {
            return;
        }

        $this->invocationQueued = true;
        $this->queue[] = ['project' => $this->profile->name(), 'domain' => $host->toString()];
    }

    /**
     * Claim the once-per-session entry signal for this package.
     *
     * Returns true only for the request that won the claim. PHP holds the
     * session for the duration of a request, so parallel tabs of the same
     * session compete for one claim rather than each sending their own.
     */
    public function claimSectionEntry(SessionInterface $session, InstallationStatus $status): bool
    {
        $key = $status->authenticatedKey();
        $host = $status->matchedHost;

        // Only a verified record may supply a key, and only an authorised
        // installation has one to supply.
        if (!$status->isEntitled() || null === $key || $key === '' || null === $host) {
            return false;
        }

        $marker = $this->profile->sessionClaimKey();

        if ($session->get($marker) !== null) {
            return false;
        }

        // Claimed before delivery: a failed send must not become a retry.
        $session->set($marker, 1);

        $this->queue[] = ['domain' => $host, 'key' => $key];

        return true;
    }

    /**
     * Drop the claim so a changed key is signalled once more in this session.
     */
    public function releaseClaim(SessionInterface $session): void
    {
        $session->remove($this->profile->sessionClaimKey());
    }

    /**
     * Deliver whatever is queued. Called after the response has been sent.
     */
    public function flush(): void
    {
        $queued = $this->queue;
        $this->queue = [];

        if ([] === $queued) {
            return;
        }

        $url = $this->endpoints->signalUrl();

        foreach ($queued as $payload) {
            try {
                $response = $this->client->request('POST', $url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'max_redirects' => 0,
                    'timeout' => self::CONNECT_TIMEOUT,
                    'max_duration' => self::MAX_DURATION,
                    'verify_peer' => true,
                    'verify_host' => true,
                ]);

                // Force the transfer, then discard: the body is of no interest
                // and must not be read, parsed or logged.
                $response->getStatusCode();
            } catch (\Throwable) {
                // Silent by design.
            }
        }
    }
}
