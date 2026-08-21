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

/**
 * Immutable product identity and the package rules this distribution accepts.
 *
 * The product is distributed under the perpetual no-charge tier: a V&T-issued
 * key is still mandatory, but the signed record must describe a perpetual term
 * (no end date). Anything else — a dated no-charge record, an evaluation record
 * or a paid-tier record — is refused, because this build has no code path for a
 * dated term and must not pretend otherwise.
 */
final class ProductProfile
{
    /** Packet schema this build understands; unchanged by the multi-host fields. */
    public const SCHEMA = 2;

    private const NAME = 'SchemaOrg';
    private const SLUG = 'schema-org';
    private const CATALOGUE_ID = 'vt-schema-org';

    /** Display title used for the administration section headline. */
    private const TITLE = 'Schema.org';

    /** The only tier value this distribution may run under. */
    private const ACCEPTED_TIERS = ['free'];

    /** Upper bound for a key as accepted by the entry field and the record. */
    public const KEY_MAX_LENGTH = 190;

    public function name(): string
    {
        return self::NAME;
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function catalogueId(): string
    {
        return self::CATALOGUE_ID;
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /** Exact public path the issuer pushes replacement records to. */
    public function intakePath(): string
    {
        return '/rest/api/v1/' . self::SLUG . '-license-updater';
    }

    /** Neutral per-session claim key; carries no key, host or session material. */
    public function sessionClaimKey(): string
    {
        return 'vts.' . self::SLUG . '.section_entry';
    }

    public function acceptsTier(string $tier): bool
    {
        return \in_array($tier, self::ACCEPTED_TIERS, true);
    }

    /**
     * This distribution runs only on a perpetual term. A dated record is refused
     * even when its signature is genuine.
     */
    public function requiresPerpetualTerm(): bool
    {
        return true;
    }

    /** A dated fallback tier can never be reached in this distribution. */
    public function allowsFallbackTier(): bool
    {
        return false;
    }

    /**
     * Shape check for an operator-entered key. Deliberately permissive about the
     * alphabet (the issuer owns the format) but strict about length, whitespace
     * and control characters so nothing odd reaches the transport.
     */
    public function looksLikeKey(string $candidate): bool
    {
        if ($candidate === '' || \strlen($candidate) > self::KEY_MAX_LENGTH) {
            return false;
        }

        return 1 === preg_match('/^[\x21-\x7e]{8,}$/', $candidate);
    }
}
