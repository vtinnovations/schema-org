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

/**
 * An exchange did not produce a usable answer.
 *
 * "Transient" separates "we could not ask" from "we asked and were told no".
 * A transient failure must leave a working installation exactly as it was — a
 * dropped connection or a bad gateway is not a revocation.
 */
final class ExchangeFailure extends \RuntimeException
{
    public const TRANSPORT = 'transport_error';
    public const SERVER_ERROR = 'remote_server_error';
    public const DENIED = 'remote_denied';
    public const MEDIA_TYPE = 'unexpected_media_type';
    public const TOO_LARGE = 'response_too_large';
    public const MALFORMED = 'response_malformed';
    public const CORRELATION = 'correlation_mismatch';
    public const CLOCK_SKEW = 'clock_skew';
    public const NO_HOST = 'no_configured_host';
    public const NO_KEY = 'no_key_available';
    public const KEY_SHAPE = 'key_shape_rejected';

    public function __construct(
        private readonly string $category,
        private readonly bool $transient = false,
    ) {
        parent::__construct($category);
    }

    public function category(): string
    {
        return $this->category;
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
