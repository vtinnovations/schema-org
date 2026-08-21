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

use Psr\Log\LoggerInterface;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * Removes the authoritative package.
 *
 * Deletes the stored pair and its rollback copies, re-evaluates and returns the
 * site to plain Contao behaviour. Configuration on pages and records is left
 * untouched, so a later activation restores the previous behaviour.
 */
final class StateRemoval
{
    public function __construct(
        private readonly RecordStore $store,
        private readonly StatusEvaluator $evaluator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function remove(): Outcome
    {
        if (!$this->store->hasPair()) {
            return Outcome::failed('no_state');
        }

        try {
            $this->store->discard();
        } catch (\Throwable) {
            return Outcome::failed('state_not_written');
        }

        $this->evaluator->forget();

        $this->logger->info('schema-org package removed', [
            'operation' => 'remove',
            'result' => 'removed',
        ]);

        return Outcome::ok('removed');
    }
}
