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

/**
 * A delivered package was not accepted.
 *
 * The category is an internal token used for diagnostics and safe logging. It is
 * never rendered verbatim and never authorises anything.
 */
final class PackageRejected extends \RuntimeException
{
    public const PAYLOAD_UNREADABLE = 'payload_unreadable';
    public const ENVELOPE_MALFORMED = 'envelope_malformed';
    public const ANCHOR_STORE_EMPTY = 'signing_key_store_empty';
    public const ANCHOR_UNKNOWN = 'unknown_signing_key';
    public const ALGORITHM_UNSUPPORTED = 'unsupported_algorithm';
    public const ENVELOPE_SIGNATURE_INVALID = 'envelope_signature_invalid';
    public const CHECKSUM_MISMATCH = 'checksum_mismatch';
    public const RECORD_MALFORMED = 'record_malformed';
    public const RECORD_SIGNATURE_INVALID = 'record_signature_invalid';
    public const SCHEMA_MISMATCH = 'schema_mismatch';
    public const PRODUCT_MISMATCH = 'product_mismatch';
    public const HOST_SET_INVALID = 'host_set_invalid';
    public const HOST_MISMATCH = 'host_mismatch';
    public const TIER_NOT_PERMITTED = 'tier_not_permitted';
    public const TERM_NOT_PERMITTED = 'term_not_permitted';
    public const DATES_INVALID = 'dates_invalid';
    public const STATUS_NOT_VALID = 'status_not_valid';
    public const VERSION_ROLLBACK = 'version_rollback';
    public const REQUEST_UNAUTHENTICATED = 'request_unauthenticated';
    public const REQUEST_STALE = 'request_stale';
    public const REQUEST_CONFLICT = 'request_conflict';
    public const REQUEST_MALFORMED = 'request_malformed';

    public function __construct(private readonly string $category)
    {
        parent::__construct($category);
    }

    public function category(): string
    {
        return $this->category;
    }
}
