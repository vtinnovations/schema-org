<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Schema\Provider;

use Doctrine\DBAL\Connection;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * Merges hand-written JSON-LD from the page (and, on detail pages, the news /
 * event record) into the graph. Lets editors add any schema.org type the
 * automatic providers do not cover — Product, HowTo, Recipe, Review, …
 */
final class CustomJsonLdProvider implements NodeProviderInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $this->merge((string) $ctx->page->schema_customJsonLd, $graph);

        if ($ctx->autoItem === null) {
            return;
        }

        foreach (['tl_news', 'tl_calendar_events'] as $table) {
            try {
                $raw = $this->connection->fetchOne(
                    "SELECT schema_customJsonLd FROM $table WHERE alias = ? LIMIT 1",
                    [$ctx->autoItem],
                );
            } catch (\Throwable) {
                continue;
            }

            if (\is_string($raw) && $raw !== '') {
                $this->merge($raw, $graph);
            }
        }
    }

    private function merge(string $raw, SchemaGraph $graph): void
    {
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return; // invalid JSON — ignore silently, never break the page
        }

        // Accept either a single node or a list of nodes.
        $nodes = isset($decoded['@type']) ? [$decoded] : $decoded;

        foreach ($nodes as $node) {
            if (\is_array($node) && $node !== []) {
                // Drop a redundant @context; the graph supplies one.
                unset($node['@context']);
                $graph->add($node);
            }
        }
    }
}
