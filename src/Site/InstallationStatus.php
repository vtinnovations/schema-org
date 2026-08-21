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

namespace VTinnovations\SchemaOrg\Site;

/**
 * The immutable outcome of evaluating this installation.
 *
 * Shared input rather than a switch: each protected boundary requests it and
 * decides for itself. No single flag enables the product.
 *
 * The full key is not part of anything exposed for display or logging.
 * {@see authenticatedKey()} is the only accessor, it is populated from a verified
 * record, and its callers are limited to the exchange transport and the session
 * entry signal.
 */
final class InstallationStatus
{
    public const ACTIVE = 'active';
    public const ABSENT = 'absent';
    public const REFUSED = 'refused';

    /**
     * @param list<string> $configuredHosts
     * @param list<string> $signedHosts
     * @param list<string> $features
     */
    private function __construct(
        public readonly string $state,
        public readonly string $category,
        public readonly array $configuredHosts,
        public readonly array $signedHosts,
        public readonly ?string $matchedHost,
        public readonly ?string $tier,
        public readonly array $features,
        public readonly ?int $version,
        public readonly ?int $issuedAt,
        public readonly ?int $endsAt,
        public readonly bool $perpetual,
        public readonly ?int $allowance,
        public readonly string $maskedKey,
        private readonly ?string $key,
    ) {
    }

    /**
     * @param list<string> $configuredHosts
     */
    public static function absent(string $category, array $configuredHosts): self
    {
        return new self(self::ABSENT, $category, $configuredHosts, [], null, null, [], null, null, null, false, null, '', null);
    }

    /**
     * A package exists but does not authorise this installation.
     *
     * @param list<string> $configuredHosts
     * @param list<string> $signedHosts
     */
    public static function refused(string $category, array $configuredHosts, array $signedHosts = []): self
    {
        return new self(self::REFUSED, $category, $configuredHosts, $signedHosts, null, null, [], null, null, null, false, null, '', null);
    }

    /**
     * @param list<string> $configuredHosts
     * @param list<string> $signedHosts
     * @param list<string> $features
     */
    public static function active(
        array $configuredHosts,
        array $signedHosts,
        string $matchedHost,
        string $tier,
        array $features,
        int $version,
        int $issuedAt,
        ?int $endsAt,
        bool $perpetual,
        int $allowance,
        string $maskedKey,
        string $key,
    ): self {
        return new self(
            self::ACTIVE,
            'ok',
            $configuredHosts,
            $signedHosts,
            $matchedHost,
            $tier,
            $features,
            $version,
            $issuedAt,
            $endsAt,
            $perpetual,
            $allowance,
            $maskedKey,
            $key,
        );
    }

    public function isEntitled(): bool
    {
        return self::ACTIVE === $this->state;
    }

    /**
     * True when the given host is exactly one of the hosts this installation is
     * authorised for. Byte equality only.
     */
    public function authorises(HostName $host): bool
    {
        if (!$this->isEntitled()) {
            return false;
        }

        return \in_array($host->toString(), $this->signedHosts, true)
            && \in_array($host->toString(), $this->configuredHosts, true);
    }

    /** Server side only. Never render, log or store this value. */
    public function authenticatedKey(): ?string
    {
        return $this->key;
    }

    /**
     * Everything here is safe for an ordinary log line: no key, no key length,
     * no digest, no host set, no signature material.
     *
     * @return array<string, scalar|null>
     */
    public function logContext(): array
    {
        return [
            'state' => $this->state,
            'category' => $this->category,
            'version' => $this->version,
            'tier' => $this->tier,
        ];
    }
}
