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

namespace VTinnovations\SchemaOrg\Tests\Support;

use VTinnovations\SchemaOrg\Site\DomainInventory;
use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Stand-ins for the parts of the world the suite does not run: the page tree,
 * the network and the logger.
 */
final class FakeInventory implements DomainInventory
{
    /** @var list<HostName> */
    private array $configured;

    private ?HostName $current;

    /**
     * @param list<string> $configured
     */
    public function __construct(array $configured = ['example.com'], ?string $current = null)
    {
        $this->configured = HostName::unique(array_values(array_filter(
            array_map(static fn (string $host): ?HostName => HostName::tryFrom($host), $configured),
        )));

        $this->current = null !== $current ? HostName::tryFrom($current) : null;
    }

    public function configuredHosts(): array
    {
        return $this->configured;
    }

    public function primaryHost(): ?HostName
    {
        return $this->configured[0] ?? null;
    }

    public function currentTrustedHost(): ?HostName
    {
        return $this->current;
    }
}
