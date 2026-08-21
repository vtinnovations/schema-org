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
 * The hosts this installation is configured to answer on.
 *
 * The inventory comes from site configuration, never from a request header a
 * caller controls. Implementations may consult the current request host only
 * through the framework's trusted-host handling.
 */
interface DomainInventory
{
    /**
     * Every trusted configured host of this installation, sorted and unique.
     *
     * @return list<HostName>
     */
    public function configuredHosts(): array;

    /** The host to use when the current request does not identify one. */
    public function primaryHost(): ?HostName;

    /** Current request host, resolved through trusted-host handling. */
    public function currentTrustedHost(): ?HostName;
}
