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

use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Where a complete package comes from when the installation asks for one.
 *
 * Kept separate from the HTTP implementation so the operations that decide what
 * to do with an answer can be exercised without a network, and so the transport
 * can be replaced without touching those decisions.
 */
interface PackageSource
{
    public const ACTIVATE = 'activate';
    public const REFRESH = 'refresh';

    /**
     * @return array{envelope: \stdClass, payload_b64: string, request_id: string}
     *
     * @throws ExchangeFailure
     */
    public function exchange(string $action, string $key, HostName $host, ?int $currentVersion, int $now): array;
}
