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
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\SealedPackage;
use VTinnovations\SchemaOrg\Remote\ExchangeFailure;
use VTinnovations\SchemaOrg\Remote\PackageSource;
use VTinnovations\SchemaOrg\Site\DomainInventory;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * The two operator-initiated exchanges: activation and refresh.
 *
 * Both fetch a complete package, verify it, evaluate it against this installation
 * and only then store it. Nothing is written half-verified, and any failure of
 * transport, denial or verification leaves the previous state untouched.
 */
final class KeyExchange
{
    public function __construct(
        private readonly RecordStore $store,
        private readonly PackageSource $client,
        private readonly PackageOpener $opener,
        private readonly StatusEvaluator $evaluator,
        private readonly DomainInventory $inventory,
        private readonly ProductProfile $profile,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Verify and activate a key the operator just entered.
     */
    public function activate(string $key, ?int $now = null): Outcome
    {
        $now ??= time();
        $key = trim($key);

        if (!$this->profile->looksLikeKey($key)) {
            return Outcome::failed(ExchangeFailure::KEY_SHAPE);
        }

        $host = $this->hostForExchange();

        if (null === $host) {
            return Outcome::failed(ExchangeFailure::NO_HOST);
        }

        return $this->run(PackageSource::ACTIVATE, $key, $host, null, $now);
    }

    /**
     * Fetch the current package again, using the stored key unless a
     * replacement was entered.
     */
    public function refresh(?string $replacement = null, ?int $now = null): Outcome
    {
        $now ??= time();
        $stored = $this->storedPackage($now);

        $key = null !== $replacement && trim($replacement) !== ''
            ? trim($replacement)
            : $stored?->key();

        if (null === $key) {
            return Outcome::failed(ExchangeFailure::NO_KEY);
        }

        if (!$this->profile->looksLikeKey($key)) {
            return Outcome::failed(ExchangeFailure::KEY_SHAPE);
        }

        $host = $this->hostForExchange($stored);

        if (null === $host) {
            return Outcome::failed(ExchangeFailure::NO_HOST);
        }

        return $this->run(PackageSource::REFRESH, $key, $host, $stored?->version(), $now);
    }

    private function run(string $action, string $key, HostName $host, ?int $currentVersion, int $now): Outcome
    {
        try {
            $answer = $this->client->exchange($action, $key, $host, $currentVersion, $now);
        } catch (ExchangeFailure $failure) {
            // Transient or denied, the previous state stays untouched either way.
            return Outcome::failed($failure->category());
        }

        try {
            $candidate = $this->opener->open($answer['envelope'], $answer['payload_b64'], $host, $now);
        } catch (PackageRejected $rejected) {
            return Outcome::failed($rejected->category());
        }

        if (!$this->supersedesStored($candidate, $now)) {
            return Outcome::failed(PackageRejected::VERSION_ROLLBACK);
        }

        $judged = $this->evaluator->judge($candidate, $now);

        if (!$judged->isEntitled()) {
            // Genuine, but it does not authorise this installation. Keep what we had.
            return Outcome::failed($judged->category);
        }

        try {
            $this->store->commit($candidate, $now);
        } catch (\Throwable) {
            return Outcome::failed('state_not_written');
        }

        $this->store->writeSidecar(['key' => '', 'category' => $action]);
        $this->evaluator->forget();

        $this->logger->info('schema-org package applied', [
            'operation' => $action,
            'result' => 'applied',
            'license_version' => $candidate->version(),
        ]);

        return Outcome::ok($action);
    }

    /**
     * An answer for the same key may never move the installation backwards. A
     * different key is a different licence, so its version is unrelated.
     */
    private function supersedesStored(SealedPackage $candidate, int $now): bool
    {
        $stored = $this->storedPackage($now);

        if (null === $stored || !hash_equals($stored->key(), $candidate->key())) {
            return true;
        }

        return $candidate->version() >= $stored->version();
    }

    private function storedPackage(int $now): ?SealedPackage
    {
        $pair = $this->store->readPair();

        if (null === $pair) {
            return null;
        }

        try {
            return $this->opener->reopen($pair['envelope'], $pair['bytes'], $now);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Deterministic choice of the host this exchange is performed for: the host
     * already bound to the stored package when it is still configured, then the
     * current trusted host when it is configured, then the site's primary host.
     * Background work has no request, which is exactly why this cannot depend
     * on one.
     */
    private function hostForExchange(?SealedPackage $stored = null): ?HostName
    {
        $configured = $this->inventory->configuredHosts();

        if ([] === $configured) {
            return null;
        }

        if (null !== $stored) {
            $bound = HostName::intersect($configured, $stored->hosts());

            if ([] !== $bound) {
                $current = $this->inventory->currentTrustedHost();

                return null !== $current && HostName::contains($bound, $current) ? $current : $bound[0];
            }
        }

        $current = $this->inventory->currentTrustedHost();

        if (null !== $current && HostName::contains($configured, $current)) {
            return $current;
        }

        return $this->inventory->primaryHost() ?? $configured[0];
    }
}
