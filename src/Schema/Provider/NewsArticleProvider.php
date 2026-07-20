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
use VTinnovations\SchemaOrg\Config\SchemaConfig;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * Article / NewsArticle / BlogPosting for a Contao news detail page. This is
 * the single most impactful type for AI citation, so it carries author
 * (Person), publisher, image, dates and a mainEntityOfPage link back.
 */
final class NewsArticleProvider implements NodeProviderInterface
{
    public function __construct(
        private readonly SchemaConfig $config,
        private readonly Connection $connection,
    ) {
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        if ($ctx->autoItem === null) {
            return;
        }

        try {
            $news = $this->connection->fetchAssociative(
                'SELECT n.*, u.name AS authorName
                   FROM tl_news n
              LEFT JOIN tl_user u ON u.id = n.author
                  WHERE n.alias = ? AND n.published = ?
                  LIMIT 1',
                [$ctx->autoItem, '1'],
            );
        } catch (\Throwable) {
            return; // news bundle not installed / column mismatch
        }

        if ($news === false) {
            return;
        }

        if (!empty($news['schema_disable'])) {
            return;
        }

        $type = trim((string) ($news['schema_articleType'] ?? ''));
        if ($type === '') {
            $type = 'NewsArticle';
        }

        $published = (int) $news['date'];
        $modified = max((int) $news['tstamp'], $published);

        $node = [
            '@type' => $type,
            '@id' => $ctx->primaryEntityId(),
            'headline' => (string) $news['headline'],
            'url' => $ctx->pageUrl,
            'datePublished' => date('c', $published),
            'dateModified' => date('c', $modified),
            'inLanguage' => $ctx->language(),
            'isPartOf' => $graph->ref($ctx->webPageId()),
            'mainEntityOfPage' => $graph->ref($ctx->webPageId()),
        ];

        $teaser = trim(strip_tags((string) ($news['teaser'] ?? '')));
        if ($teaser !== '') {
            $node['description'] = $teaser;
        }

        // Image
        if (!empty($news['addImage']) && !empty($news['singleSRC'])) {
            $image = $this->config->fileUrl($news['singleSRC'], $ctx->baseUrl);
            if ($image !== '') {
                $node['image'] = ['@type' => 'ImageObject', 'url' => $image, 'contentUrl' => $image];
            }
        }

        // Author (Person) — override name wins, else the Contao editor name.
        $authorName = trim((string) ($news['schema_authorName'] ?? ''));
        if ($authorName === '') {
            $authorName = trim((string) ($news['authorName'] ?? ''));
        }
        if ($authorName !== '') {
            $author = ['@type' => 'Person', 'name' => $authorName];
            $authorUrl = trim((string) ($news['schema_authorUrl'] ?? ''));
            if ($authorUrl !== '') {
                $author['url'] = $authorUrl;
            }
            $node['author'] = $author;
        }

        // Publisher = the site Organization node.
        if ($this->config->showsOrganization($ctx->rootPage)) {
            $node['publisher'] = $graph->ref($ctx->organizationId());
        }

        $speakable = $this->speakable($news['schema_speakable'] ?? '');
        if ($speakable !== null) {
            $node['speakable'] = $speakable;
        }

        $graph->add($node);

        // Make the article the main entity of the page for answer engines.
        $graph->add([
            '@id' => $ctx->webPageId(),
            'mainEntity' => $graph->ref($ctx->primaryEntityId()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function speakable($raw): ?array
    {
        $selectors = array_values(array_filter(
            array_map('trim', explode(',', (string) $raw)),
            static fn (string $s): bool => $s !== '',
        ));

        return $selectors === [] ? null : ['@type' => 'SpeakableSpecification', 'cssSelector' => $selectors];
    }
}
