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

use VTinnovations\SchemaOrg\Site\HostName;

/**
 * A package that has passed every authenticity check.
 *
 * Holds the record bytes exactly as delivered, since the envelope checksum is
 * defined over them; they are never re-serialised. Created only by
 * {@see PackageOpener}.
 */
final class SealedPackage
{
    /**
     * @param list<HostName> $hosts
     * @param list<string>   $features
     *
     * @internal use PackageOpener
     */
    public function __construct(
        private readonly string $bytes,
        private readonly string $envelopeJson,
        private readonly string $key,
        private readonly HostName $operationHost,
        private readonly array $hosts,
        private readonly int $allowance,
        private readonly string $tier,
        private readonly array $features,
        private readonly int $version,
        private readonly int $issuedAt,
        private readonly int $startsAt,
        private readonly ?int $endsAt,
        private readonly bool $perpetual,
        private readonly int $verifiedAt,
        private readonly bool $fallbackOffered,
    ) {
    }

    /** Exact record bytes; the checksum is defined over these. */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /** Canonical form of the authenticated envelope, including its signature. */
    public function envelopeJson(): string
    {
        return $this->envelopeJson;
    }

    /**
     * The full key. Server side only: it may reach the issuer during an
     * exchange and the session entry signal, and nowhere else — never the
     * browser, ordinary logs, diagnostics or a claim marker.
     */
    public function key(): string
    {
        return $this->key;
    }

    /** Host this package was issued for during the current operation. */
    public function operationHost(): HostName
    {
        return $this->operationHost;
    }

    /**
     * The complete signed host set. Authorisation comes only from exact
     * membership here.
     *
     * @return list<HostName>
     */
    public function hosts(): array
    {
        return $this->hosts;
    }

    /**
     * Reported allowance. Informational only: the issuer owns binding, 9999 is
     * not a wildcard, and a bound set larger than the allowance stays valid.
     */
    public function allowance(): int
    {
        return $this->allowance;
    }

    public function tier(): string
    {
        return $this->tier;
    }

    /** @return list<string> */
    public function features(): array
    {
        return $this->features;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function issuedAt(): int
    {
        return $this->issuedAt;
    }

    public function startsAt(): int
    {
        return $this->startsAt;
    }

    public function endsAt(): ?int
    {
        return $this->endsAt;
    }

    public function isPerpetual(): bool
    {
        return $this->perpetual;
    }

    public function verifiedAt(): int
    {
        return $this->verifiedAt;
    }

    public function fallbackOffered(): bool
    {
        return $this->fallbackOffered;
    }

    /** First and last four characters only; safe for the administration screen. */
    public function maskedKey(): string
    {
        if (\strlen($this->key) <= 8) {
            return str_repeat('•', 8);
        }

        return substr($this->key, 0, 4) . '••••' . substr($this->key, -4);
    }
}
