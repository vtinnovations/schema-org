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

namespace VTinnovations\SchemaOrg\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Operation\KeyExchange;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * Daily background refresh, so a changed licence takes effect without an
 * operator re-entering anything.
 *
 * There is no request here, so the host is taken from the stored package rather
 * than from anything ambient. A failed refresh changes nothing: the licence
 * that worked yesterday still works today.
 */
#[AsCronJob('daily')]
final class StateRefreshCron
{
    /** Skip the call when the stored package was verified recently. */
    private const MAX_AGE = 43200;

    public function __construct(
        private readonly RecordStore $store,
        private readonly PackageOpener $opener,
        private readonly KeyExchange $exchange,
    ) {
    }

    public function __invoke(): void
    {
        $pair = $this->store->readPair();

        if (null === $pair) {
            return;
        }

        $now = time();

        try {
            $stored = $this->opener->reopen($pair['envelope'], $pair['bytes'], $now);
        } catch (\Throwable) {
            // Nothing usable to refresh from; an operator has to act.
            return;
        }

        if ($now - $stored->verifiedAt() < self::MAX_AGE) {
            return;
        }

        $this->exchange->refresh(null, $now);
    }
}
