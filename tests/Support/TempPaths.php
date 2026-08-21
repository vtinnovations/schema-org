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

use VTinnovations\SchemaOrg\Config\Paths;

/**
 * A throwaway state directory that removes itself.
 */
final class TempPaths
{
    public readonly Paths $paths;

    private readonly string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/vts-schema-' . bin2hex(random_bytes(6));
        $this->paths = new Paths($this->root);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function destroy(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($this->root);
    }
}
