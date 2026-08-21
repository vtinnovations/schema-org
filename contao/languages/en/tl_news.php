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

$GLOBALS['TL_LANG']['tl_news']['schema_legend'] = 'Schema.org';

$GLOBALS['TL_LANG']['tl_news']['schema_articleTypeOptions'] = [
    'NewsArticle' => 'News article',
    'Article' => 'Article (generic)',
    'BlogPosting' => 'Blog post',
    'ReportageNewsArticle' => 'Reportage',
    'OpinionNewsArticle' => 'Opinion piece',
];

$GLOBALS['TL_LANG']['tl_news']['schema_disable'] = ['Disable schema', 'No Article JSON-LD for this news item.'];
$GLOBALS['TL_LANG']['tl_news']['schema_articleType'] = ['Article type', 'Empty = NewsArticle.'];
$GLOBALS['TL_LANG']['tl_news']['schema_authorName'] = ['Author (name)', 'Overrides the Contao author. Emitted as a Person.'];
$GLOBALS['TL_LANG']['tl_news']['schema_authorUrl'] = ['Author (URL)', 'Author profile/about URL.'];
$GLOBALS['TL_LANG']['tl_news']['schema_speakable'] = ['Speakable CSS selectors', 'Comma-separated. Marks read-aloud regions for voice/AI assistants.'];
$GLOBALS['TL_LANG']['tl_news']['schema_customJsonLd'] = ['Custom JSON-LD', 'Additional nodes for the @graph (without @context).'];
