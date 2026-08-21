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

namespace VTinnovations\SchemaOrg\Storage;

use VTinnovations\SchemaOrg\Config\Paths;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Intake\PackageOpener;
use VTinnovations\SchemaOrg\Intake\PackageRejected;
use VTinnovations\SchemaOrg\Intake\SealedPackage;

/**
 * The authoritative store for the current package.
 *
 * Record bytes and envelope are two files but one unit: written, verified and
 * swapped together, with both restored to the previous pair if anything fails
 * after the swap. Decides nothing about entitlement; it stores bytes and returns
 * them for re-verification.
 */
final class RecordStore
{
    private int $lockDepth = 0;

    /** @var resource|null */
    private $lockHandle;

    public function __construct(
        private readonly Paths $paths,
        private readonly PackageOpener $opener,
        private readonly ProductProfile $profile,
    ) {
    }

    /**
     * The stored pair, or null when this installation holds no package.
     *
     * @return array{bytes: string, envelope: string}|null
     */
    public function readPair(): ?array
    {
        $bytes = @file_get_contents($this->paths->recordFile());
        $envelope = @file_get_contents($this->paths->sealFile());

        if (!\is_string($bytes) || !\is_string($envelope) || $bytes === '' || $envelope === '') {
            return null;
        }

        return ['bytes' => $bytes, 'envelope' => $envelope];
    }

    public function hasPair(): bool
    {
        return null !== $this->readPair();
    }

    /**
     * Replace the stored pair with an already authenticated package.
     *
     * @throws PackageRejected  when the candidate or the written result does
     *                          not verify; the previous pair is restored first
     * @throws \RuntimeException on an unusable state directory
     */
    public function commit(SealedPackage $package, int $now): void
    {
        $this->withLock(function () use ($package, $now): void {
            // Validate the candidate once more before touching anything live.
            $this->opener->reopen($package->envelopeJson(), $package->bytes(), $now);

            $recordFile = $this->paths->recordFile();
            $sealFile = $this->paths->sealFile();
            $suffix = '.' . bin2hex(random_bytes(6)) . '.tmp';

            $recordTmp = $recordFile . $suffix;
            $sealTmp = $sealFile . $suffix;

            try {
                $this->writeExact($recordTmp, $package->bytes());
                $this->writeExact($sealTmp, $package->envelopeJson());

                // Read back what actually landed on disk, not what we meant to write.
                $this->opener->reopen($this->readExact($sealTmp), $this->readExact($recordTmp), $now);

                $backup = $this->backupCurrent();

                if (!@rename($recordTmp, $recordFile) || !@rename($sealTmp, $sealFile)) {
                    $this->restore($backup);

                    throw new \RuntimeException('Could not activate the new state.');
                }

                try {
                    $this->opener->reopen($this->readExact($sealFile), $this->readExact($recordFile), $now);
                } catch (PackageRejected | \RuntimeException $e) {
                    $this->restore($backup);

                    throw $e;
                }

                $this->dropBackup($backup);
            } finally {
                @unlink($recordTmp);
                @unlink($sealTmp);
            }
        });
    }

    /**
     * Remove the authoritative pair. The product returns to its unlicensed
     * behaviour immediately; nothing else stored by the site is touched.
     */
    public function discard(): void
    {
        $this->withLock(function (): void {
            foreach ([$this->paths->recordFile(), $this->paths->sealFile()] as $file) {
                @unlink($file);
                @unlink($file . '.bak');
            }

            $this->writeSidecar(['key' => '', 'category' => 'removed_by_operator']);
        });
    }

    /**
     * Non-authoritative operational notes. Holds a key adopted from a
     * superseded store so an operator can activate it, and the last result
     * category for the screen. It grants nothing on its own.
     *
     * @return array{key: string, category: string, at: int}
     */
    public function sidecar(): array
    {
        $raw = @file_get_contents($this->paths->sidecarFile());
        $data = \is_string($raw) ? json_decode($raw, true) : null;

        if (!\is_array($data)) {
            $data = [];
        }

        return [
            'key' => \is_string($data['key'] ?? null) ? $data['key'] : '',
            'category' => \is_string($data['category'] ?? null) ? $data['category'] : '',
            'at' => (int) ($data['at'] ?? 0),
        ];
    }

    /**
     * @param array{key?: string, category?: string} $patch
     */
    public function writeSidecar(array $patch): void
    {
        $merged = array_merge($this->sidecar(), $patch, ['at' => time()]);
        $json = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            $this->writeExact($this->paths->sidecarFile(), $json);
        } catch (\RuntimeException) {
            // The sidecar is a convenience; losing it must not break anything.
        }
    }

    /**
     * Take over a key written by a release before 2.0.
     *
     * That store was never signed, so it cannot grant anything under the
     * current rules and is not treated as state. The key is kept server side so
     * an operator can activate it in one step, and the old file is removed so
     * two stores can never disagree.
     */
    public function adoptSupersededState(): bool
    {
        $legacy = $this->paths->supersededStateFile();

        if (!is_file($legacy)) {
            return false;
        }

        return (bool) $this->withLock(function () use ($legacy): bool {
            $adopted = false;
            $raw = @file_get_contents($legacy);
            $data = \is_string($raw) ? json_decode($raw, true) : null;

            if (\is_array($data)) {
                $key = trim((string) ($data['license_key'] ?? ''));

                if ($this->profile->looksLikeKey($key) && $this->sidecar()['key'] === '') {
                    $this->writeSidecar(['key' => $key, 'category' => 'adopted_pending_activation']);
                    $adopted = true;
                }
            }

            @unlink($legacy);

            return $adopted;
        });
    }

    /**
     * Run a callable while holding the exclusive state lock. Re-entrant, so an
     * operation may call another without deadlocking itself.
     */
    public function withLock(callable $operation): mixed
    {
        if ($this->lockDepth > 0) {
            ++$this->lockDepth;

            try {
                return $operation();
            } finally {
                --$this->lockDepth;
            }
        }

        $handle = @fopen($this->paths->lockFile(), 'c');

        if (false === $handle) {
            throw new \RuntimeException('Could not open the state lock.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new \RuntimeException('Could not acquire the state lock.');
        }

        $this->lockHandle = $handle;
        $this->lockDepth = 1;

        try {
            return $operation();
        } finally {
            $this->lockDepth = 0;
            $this->lockHandle = null;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, string> live file to backup file for files that existed
     */
    private function backupCurrent(): array
    {
        $backup = [];

        foreach ([$this->paths->recordFile(), $this->paths->sealFile()] as $file) {
            if (is_file($file)) {
                $copy = $file . '.bak';

                if (@copy($file, $copy)) {
                    @chmod($copy, 0600);
                    $backup[$file] = $copy;
                }
            }
        }

        return $backup;
    }

    /**
     * @param array<string, string> $backup
     */
    private function restore(array $backup): void
    {
        foreach ([$this->paths->recordFile(), $this->paths->sealFile()] as $file) {
            if (isset($backup[$file])) {
                @rename($backup[$file], $file);
            } else {
                // Nothing was stored before: leave nothing behind either.
                @unlink($file);
            }
        }
    }

    /**
     * @param array<string, string> $backup
     */
    private function dropBackup(array $backup): void
    {
        foreach ($backup as $copy) {
            @unlink($copy);
        }
    }

    private function writeExact(string $file, string $contents): void
    {
        $handle = @fopen($file, 'wb');

        if (false === $handle) {
            throw new \RuntimeException(sprintf('Could not write "%s".', basename($file)));
        }

        try {
            if (false === fwrite($handle, $contents) || !fflush($handle)) {
                throw new \RuntimeException(sprintf('Could not write "%s".', basename($file)));
            }

            if (\function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        @chmod($file, 0600);
    }

    private function readExact(string $file): string
    {
        clearstatcache(true, $file);
        $contents = @file_get_contents($file);

        if (!\is_string($contents) || $contents === '') {
            throw new \RuntimeException(sprintf('Could not read back "%s".', basename($file)));
        }

        return $contents;
    }
}
