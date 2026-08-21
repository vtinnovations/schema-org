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
 * One exact host, normalised in representation only.
 *
 * Normalisation lowercases ASCII, removes a single trailing dot, removes a port and
 * converts an internationalised name to Punycode. It never changes which host is
 * meant: "example.com", "www.example.com", "shop.example.com" and
 * "a.shop.example.com" remain four distinct identities. Comparison is byte
 * equality; there is no suffix, wildcard, registrable-domain or parent/child
 * relationship.
 *
 * Wildcards, credentials, paths, IP literals and single-label names are rejected,
 * so an IP or "localhost" installation cannot be activated.
 */
final class HostName
{
    private const MAX_LENGTH = 253;
    private const MAX_LABEL = 63;

    private function __construct(private readonly string $value)
    {
    }

    public static function tryFrom(string $candidate): ?self
    {
        $candidate = trim($candidate);

        if ($candidate === '' || \strlen($candidate) > 400) {
            return null;
        }

        // No scheme, path, credentials, wildcard or whitespace may survive.
        if (1 === preg_match('~[\s/\\\\@?#*\[\]]~', $candidate)) {
            return null;
        }

        $candidate = self::stripPort($candidate);
        if (null === $candidate) {
            return null;
        }

        if (str_ends_with($candidate, '.')) {
            $candidate = substr($candidate, 0, -1);
        }

        $candidate = self::toAscii($candidate);
        if (null === $candidate) {
            return null;
        }

        if (!self::isPlausibleName($candidate)) {
            return null;
        }

        return new self($candidate);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Sorted, de-duplicated exact hosts.
     *
     * @param list<self> $hosts
     *
     * @return list<self>
     */
    public static function unique(array $hosts): array
    {
        $byValue = [];

        foreach ($hosts as $host) {
            $byValue[$host->value] = $host;
        }

        uksort($byValue, static fn (string $a, string $b): int => strcmp($a, $b));

        return array_values($byValue);
    }

    /**
     * @param list<self> $left
     * @param list<self> $right
     *
     * @return list<self> exact members of both sets
     */
    public static function intersect(array $left, array $right): array
    {
        $rightValues = [];
        foreach ($right as $host) {
            $rightValues[$host->value] = true;
        }

        $shared = [];
        foreach ($left as $host) {
            if (isset($rightValues[$host->value])) {
                $shared[] = $host;
            }
        }

        return self::unique($shared);
    }

    /**
     * @param list<self> $haystack
     */
    public static function contains(array $haystack, self $needle): bool
    {
        foreach ($haystack as $host) {
            if ($host->equals($needle)) {
                return true;
            }
        }

        return false;
    }

    private static function stripPort(string $candidate): ?string
    {
        $colon = strrpos($candidate, ':');

        if (false === $colon) {
            return $candidate;
        }

        $port = substr($candidate, $colon + 1);

        if ($port === '' || 1 !== preg_match('/^\d{1,5}$/', $port)) {
            return null;
        }

        return substr($candidate, 0, $colon);
    }

    private static function toAscii(string $candidate): ?string
    {
        if (1 === preg_match('/^[\x20-\x7e]*$/', $candidate)) {
            return strtolower($candidate);
        }

        if (!\function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($candidate, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return \is_string($ascii) && $ascii !== '' ? strtolower($ascii) : null;
    }

    private static function isPlausibleName(string $candidate): bool
    {
        if ($candidate === '' || \strlen($candidate) > self::MAX_LENGTH) {
            return false;
        }

        if (false !== filter_var($candidate, FILTER_VALIDATE_IP)) {
            return false;
        }

        $labels = explode('.', $candidate);

        // A single label cannot identify a licensed installation.
        if (\count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || \strlen($label) > self::MAX_LABEL) {
                return false;
            }

            if (1 !== preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
                return false;
            }
        }

        // A purely numeric last label would mean an address, not a name.
        return 1 !== preg_match('/^\d+$/', $labels[array_key_last($labels)]);
    }
}
