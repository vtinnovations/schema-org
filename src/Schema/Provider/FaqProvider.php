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
 * FAQPage node built from Contao FAQ categories whose reader page (jumpTo) is
 * the current page. FAQ markup is the highest-leverage type for AI Overview
 * citations, so every published question on the page is emitted as a Question
 * with an acceptedAnswer.
 */
final class FaqProvider implements NodeProviderInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT f.question, f.answer
                   FROM tl_faq f
              INNER JOIN tl_faq_category c ON c.id = f.pid
                  WHERE c.jumpTo = ? AND f.published = ?
                    AND (f.schema_disable IS NULL OR f.schema_disable = ?)
               ORDER BY f.sorting',
                [(int) $ctx->page->id, '1', ''],
            );
        } catch (\Throwable) {
            return; // faq bundle not installed
        }

        $questions = [];
        foreach ($rows as $row) {
            $name = trim(strip_tags((string) $row['question']));
            $answer = $this->plainText((string) $row['answer']);
            if ($name === '' || $answer === '') {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => $name,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        if ($questions === []) {
            return;
        }

        $faqId = $ctx->pageUrl . '#faqpage';

        $graph->add([
            '@type' => 'FAQPage',
            '@id' => $faqId,
            'url' => $ctx->pageUrl,
            'inLanguage' => $ctx->language(),
            'mainEntity' => $questions,
        ]);

        $graph->add([
            '@id' => $ctx->webPageId(),
            'mainEntity' => $graph->ref($faqId),
        ]);
    }

    private function plainText(string $html): string
    {
        // DB answers can hold unresolved insert tags; drop them + tags so no
        // raw {{...}} markup leaks into the structured data.
        $text = preg_replace('/\{\{[^}]*}}/', '', $html) ?? $html;

        return trim(strip_tags($text));
    }
}
