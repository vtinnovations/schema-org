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

use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\SealedPackage;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * Evaluates the installation against the stored package.
 *
 * The package is re-verified on every evaluation rather than trusted from a
 * previous one, so an edited record, an older pair or state copied from another
 * site all fail here. The result is memoised per request only.
 */
final class StatusEvaluator
{
    private ?InstallationStatus $memo = null;

    public function __construct(
        private readonly RecordStore $store,
        private readonly PackageOpener $opener,
        private readonly DomainInventory $inventory,
    ) {
    }

    public function current(?int $now = null): InstallationStatus
    {
        if (null !== $this->memo) {
            return $this->memo;
        }

        $now ??= time();
        $pair = $this->store->readPair();

        if (null === $pair) {
            return $this->memo = InstallationStatus::absent('no_state', $this->configuredHostNames());
        }

        try {
            $package = $this->opener->reopen($pair['envelope'], $pair['bytes'], $now);
        } catch (PackageRejected $rejected) {
            return $this->memo = InstallationStatus::refused($rejected->category(), $this->configuredHostNames());
        } catch (\Throwable) {
            return $this->memo = InstallationStatus::refused('state_unreadable', $this->configuredHostNames());
        }

        return $this->memo = $this->judge($package, $now);
    }

    /**
     * Judge a package without storing it, so a candidate can be rejected before
     * it replaces something that currently works.
     */
    public function judge(SealedPackage $package, int $now): InstallationStatus
    {
        $configured = $this->inventory->configuredHosts();
        $signed = $package->hosts();
        $signedNames = array_map(static fn (HostName $h): string => $h->toString(), $signed);

        if ([] === $configured) {
            return InstallationStatus::refused('no_configured_host', [], $signedNames);
        }

        $shared = HostName::intersect($configured, $signed);

        if ([] === $shared) {
            // The package is genuine but was not issued for any host this
            // installation actually serves.
            return InstallationStatus::refused('host_not_configured', $this->names($configured), $signedNames);
        }

        if (!$package->isPerpetual() && null !== $package->endsAt() && $package->endsAt() <= $now) {
            return InstallationStatus::refused('term_ended', $this->names($configured), $signedNames);
        }

        return InstallationStatus::active(
            $this->names($configured),
            $signedNames,
            $this->selectMatched($shared)->toString(),
            $package->tier(),
            $package->features(),
            $package->version(),
            $package->issuedAt(),
            $package->endsAt(),
            $package->isPerpetual(),
            $package->allowance(),
            $package->maskedKey(),
            $package->key(),
        );
    }

    /** Drop the memo after a state change so the screen shows the new truth. */
    public function forget(): void
    {
        $this->memo = null;
    }

    /**
     * Deterministic choice: the current trusted host when it is itself
     * authorised, otherwise the first authorised host in sorted order. Never an
     * arbitrary header value.
     *
     * @param list<HostName> $shared
     */
    private function selectMatched(array $shared): HostName
    {
        $current = $this->inventory->currentTrustedHost();

        if (null !== $current && HostName::contains($shared, $current)) {
            return $current;
        }

        return $shared[0];
    }

    /**
     * @return list<string>
     */
    private function configuredHostNames(): array
    {
        return $this->names($this->inventory->configuredHosts());
    }

    /**
     * @param list<HostName> $hosts
     *
     * @return list<string>
     */
    private function names(array $hosts): array
    {
        return array_map(static fn (HostName $host): string => $host->toString(), $hosts);
    }
}
