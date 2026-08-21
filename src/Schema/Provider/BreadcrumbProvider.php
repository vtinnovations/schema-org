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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * BreadcrumbList built from the page trail, and wired onto the WebPage node
 * via a back-patched breadcrumb reference.
 */
final class BreadcrumbProvider implements NodeProviderInterface
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $trail = array_map('intval', (array) $ctx->page->trail);
        if ($trail === []) {
            return;
        }

        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $items = [];
        $position = 1;

        foreach ($trail as $pageId) {
            $page = $pageAdapter->findByPk($pageId);
            if ($page === null || $page->type === 'root') {
                continue;
            }

            try {
                $url = $page->getAbsoluteUrl();
            } catch (\Throwable) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => (string) $page->title,
                'item' => $url,
            ];
        }

        if (\count($items) < 2) {
            return; // a single-item breadcrumb is noise
        }

        $graph->add([
            '@type' => 'BreadcrumbList',
            '@id' => $ctx->breadcrumbId(),
            'itemListElement' => $items,
        ]);

        // Back-patch the WebPage node (merge only adds the missing key).
        $graph->add([
            '@id' => $ctx->webPageId(),
            'breadcrumb' => $graph->ref($ctx->breadcrumbId()),
        ]);
    }
}
