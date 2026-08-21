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

namespace VTinnovations\SchemaOrg\Operation;

/**
 * Result of an operator-initiated operation.
 *
 * The category is an internal token. The screen maps it to a general message
 * instead of displaying it.
 */
final class Outcome
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $category,
    ) {
    }

    public static function ok(string $category): self
    {
        return new self(true, $category);
    }

    public static function failed(string $category): self
    {
        return new self(false, $category);
    }
}
