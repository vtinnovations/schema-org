<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Schema;

use Contao\PageModel;

/**
 * Immutable per-request bag handed to every node provider. Holds the resolved
 * page + root page, the canonical URLs and the stable @id anchors so providers
 * link to each other without re-deriving them.
 */
final class SchemaContext
{
    public function __construct(
        public readonly PageModel $page,
        public readonly PageModel $rootPage,
        public readonly string $baseUrl,
        public readonly string $pageUrl,
        public readonly ?string $autoItem,
    ) {
    }

    /** Stable node ids, anchored to the site root so every page agrees on them. */
    public function organizationId(): string
    {
        return $this->baseUrl . '/#organization';
    }

    public function webSiteId(): string
    {
        return $this->baseUrl . '/#website';
    }

    public function webPageId(): string
    {
        return $this->pageUrl . '#webpage';
    }

    public function breadcrumbId(): string
    {
        return $this->pageUrl . '#breadcrumb';
    }

    public function primaryEntityId(): string
    {
        return $this->pageUrl . '#primaryentity';
    }

    public function language(): string
    {
        $lang = (string) $this->page->language;

        return $lang !== '' ? $lang : 'de';
    }
}
