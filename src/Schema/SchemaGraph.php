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

namespace VTinnovations\SchemaOrg\Schema;

/**
 * Collects schema.org nodes for a single request and renders them as one
 * connected JSON-LD "@graph" document.
 *
 * Nodes carrying an "@id" are stored keyed by that id, so a later provider can
 * reference an earlier node with {@see ref()} without duplicating it (2026
 * best practice: one @graph, cross-linked by @id — not N loose <script> blocks).
 */
final class SchemaGraph
{
    /** @var array<string, array<string, mixed>> nodes keyed by @id */
    private array $keyed = [];

    /** @var list<array<string, mixed>> nodes without an @id */
    private array $loose = [];

    /**
     * @param array<string, mixed> $node
     */
    public function add(array $node): void
    {
        $id = $node['@id'] ?? null;

        if (\is_string($id) && $id !== '') {
            // First writer wins; later contributions merge missing keys in so a
            // stub reference created earlier gets fleshed out, never overwritten.
            $this->keyed[$id] = array_merge($node, $this->keyed[$id] ?? []);

            return;
        }

        $this->loose[] = $node;
    }

    public function has(string $id): bool
    {
        return isset($this->keyed[$id]);
    }

    /**
     * A lightweight reference to another node: {"@id": "..."}.
     *
     * @return array{'@id': string}
     */
    public function ref(string $id): array
    {
        return ['@id' => $id];
    }

    public function isEmpty(): bool
    {
        return $this->keyed === [] && $this->loose === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $graph = array_merge(array_values($this->keyed), $this->loose);

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    public function toJson(): string
    {
        $json = json_encode(
            $this->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return $json === false ? '' : $json;
    }

    /**
     * Ready-to-inject <script> element (empty string when nothing collected).
     */
    public function toScript(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $json = $this->toJson();
        if ($json === '') {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}
