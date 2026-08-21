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

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the configured hosts from the Contao page tree.
 *
 * The package is instance wide, so the inventory is the union of the domains
 * configured on every root page. Contao allows a root page to leave its domain
 * blank, which means "answer on whatever host arrives"; when no root page names
 * a domain at all there is nothing configured to compare against, and the
 * current request host — resolved by Symfony, so subject to the trusted host
 * and trusted proxy settings — is used instead. Installations that care about
 * this should set the domain on their root pages, which is also what makes the
 * host set predictable for the issuer.
 */
final class RootPageDomains implements DomainInventory
{
    /** @var list<HostName>|null */
    private ?array $configured = null;

    private bool $primaryResolved = false;

    private ?HostName $primary = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function configuredHosts(): array
    {
        if (null !== $this->configured) {
            return $this->configured;
        }

        $hosts = [];

        foreach ($this->rootRows() as $row) {
            $host = HostName::tryFrom((string) ($row['dns'] ?? ''));

            if (null !== $host) {
                $hosts[] = $host;
            }
        }

        if ([] === $hosts) {
            $current = $this->currentTrustedHost();

            if (null !== $current) {
                $hosts[] = $current;
            }
        }

        return $this->configured = HostName::unique($hosts);
    }

    public function primaryHost(): ?HostName
    {
        if ($this->primaryResolved) {
            return $this->primary;
        }

        $this->primaryResolved = true;

        foreach ($this->rootRows() as $row) {
            if ((string) ($row['fallback'] ?? '') === '1') {
                $host = HostName::tryFrom((string) ($row['dns'] ?? ''));

                if (null !== $host) {
                    return $this->primary = $host;
                }
            }
        }

        return $this->primary = $this->configuredHosts()[0] ?? null;
    }

    public function currentTrustedHost(): ?HostName
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return null;
        }

        try {
            // Symfony validates this against the trusted host patterns and the
            // trusted proxy configuration; it is not the raw Host header.
            return HostName::tryFrom($request->getHost());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rootRows(): array
    {
        try {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->connection->fetchAllAssociative(
                "SELECT dns, fallback FROM tl_page WHERE type = 'root' ORDER BY sorting",
            );

            return $rows;
        } catch (\Throwable) {
            // No database yet, or an installation mid-update: an empty
            // inventory simply means nothing can be activated right now.
            return [];
        }
    }
}
