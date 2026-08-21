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

namespace VTinnovations\SchemaOrg\Config;

/**
 * Working paths under the configured scratch directory (var/schema-org).
 *
 * Everything here is private state that must stay outside the document root and
 * outside version control. No path is ever derived from request data — the
 * scratch directory is a container parameter and the file names are fixed.
 */
final class Paths
{
    private readonly string $scratchDir;

    public function __construct(string $scratchDir)
    {
        $this->scratchDir = rtrim($scratchDir, '/\\');
    }

    public function scratch(): string
    {
        return $this->ensure($this->scratchDir);
    }

    public function stateDir(): string
    {
        return $this->ensure($this->scratchDir . '/state');
    }

    /** Exact record bytes as delivered. */
    public function recordFile(): string
    {
        return $this->stateDir() . '/record.json';
    }

    /** Authenticated envelope belonging to the record above. */
    public function sealFile(): string
    {
        return $this->stateDir() . '/seal.json';
    }

    /**
     * Operational sidecar: never authoritative, never a source of entitlement.
     * Holds an adopted key awaiting activation and the last result category.
     */
    public function sidecarFile(): string
    {
        return $this->stateDir() . '/sidecar.json';
    }

    /** Replay and idempotency records for inbound pushes. */
    public function journalFile(): string
    {
        return $this->ensure($this->scratchDir . '/journal') . '/exchange.json';
    }

    public function lockFile(): string
    {
        return $this->stateDir() . '/.lock';
    }

    /** Store written by releases before 2.0; read once, then removed. */
    public function supersededStateFile(): string
    {
        return $this->scratchDir . '/license.json';
    }

    private function ensure(string $dir): string
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Could not create directory "%s".', $dir));
            }

            @chmod($dir, 0700);
        }

        return $dir;
    }
}
