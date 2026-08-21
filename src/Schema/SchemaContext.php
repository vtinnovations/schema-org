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

    /**
     * The page language, falling back to the language of its site root.
     *
     * Returns an empty string when neither is set. Callers then omit
     * "inLanguage" rather than guessing: naming the wrong language in
     * structured data is worse than not naming one.
     */
    public function language(): string
    {
        foreach ([$this->page->language, $this->rootPage->language] as $candidate) {
            $language = trim((string) $candidate);

            if ($language !== '') {
                return $language;
            }
        }

        return '';
    }
}
