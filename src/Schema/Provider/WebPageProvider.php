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

namespace VTinnovations\SchemaOrg\Schema\Provider;

use Doctrine\DBAL\Connection;
use VTinnovations\SchemaOrg\Config\SchemaConfig;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * The WebPage node for the current URL: isPartOf the WebSite, about the
 * Organization, dateModified from the freshest content on the page, plus an
 * optional speakable spec for voice/AI answer engines.
 */
final class WebPageProvider implements NodeProviderInterface
{
    public function __construct(
        private readonly SchemaConfig $config,
        private readonly Connection $connection,
    ) {
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $page = $ctx->page;
        $root = $ctx->rootPage;

        $type = trim((string) $page->schema_webPageType);
        if ($type === '') {
            $type = 'WebPage';
        }

        $name = trim((string) $page->pageTitle);
        if ($name === '') {
            $name = trim((string) $page->title);
        }

        $node = [
            '@type' => $type,
            '@id' => $ctx->webPageId(),
            'url' => $ctx->pageUrl,
            'name' => $name,
        ];

        if ($ctx->language() !== '') {
            $node['inLanguage'] = $ctx->language();
        }

        $description = trim((string) $page->description);
        if ($description !== '') {
            $node['description'] = $description;
        }

        if ($this->config->showsWebSite($root)) {
            $node['isPartOf'] = $graph->ref($ctx->webSiteId());
        }

        if ($this->config->showsOrganization($root)) {
            $node['about'] = $graph->ref($ctx->organizationId());
            $node['publisher'] = $graph->ref($ctx->organizationId());
        }

        $modified = $this->freshestTimestamp($page->id, (int) $page->tstamp);
        if ($modified > 0) {
            $node['dateModified'] = date('c', $modified);
        }

        $speakable = $this->speakable($page->schema_speakable);
        if ($speakable !== null) {
            $node['speakable'] = $speakable;
        }

        $graph->add($node);
    }

    /**
     * Freshness signal: newest of the page row and its published articles.
     * AI engines lean on dateModified heavily, so keep it honest.
     */
    private function freshestTimestamp(int $pageId, int $pageTstamp): int
    {
        $modified = $pageTstamp;

        try {
            $articleMax = $this->connection->fetchOne(
                "SELECT MAX(tstamp) FROM tl_article WHERE pid = ? AND published = '1'",
                [$pageId],
            );
            $modified = max($modified, (int) $articleMax);
        } catch (\Throwable) {
            // tl_article always exists in a Contao install; ignore if not.
        }

        return $modified;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function speakable($raw): ?array
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $selectors = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $s): bool => $s !== '',
        ));

        if ($selectors === []) {
            return null;
        }

        return [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => $selectors,
        ];
    }
}
