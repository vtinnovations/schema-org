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
    'NewsArticle' => 'Nachrichtenartikel',
    'Article' => 'Artikel (allgemein)',
    'BlogPosting' => 'Blogbeitrag',
    'ReportageNewsArticle' => 'Reportage',
    'OpinionNewsArticle' => 'Kommentar/Meinungsbeitrag',
];

$GLOBALS['TL_LANG']['tl_news']['schema_disable'] = ['Schema deaktivieren', 'Kein Article-JSON-LD für diese Nachricht.'];
$GLOBALS['TL_LANG']['tl_news']['schema_articleType'] = ['Article-Typ', 'Leer = NewsArticle.'];
$GLOBALS['TL_LANG']['tl_news']['schema_authorName'] = ['Autor (Name)', 'Überschreibt den Contao-Autor. Wird als Person ausgegeben.'];
$GLOBALS['TL_LANG']['tl_news']['schema_authorUrl'] = ['Autor (URL)', 'Profil-/Über-mich-URL des Autors.'];
$GLOBALS['TL_LANG']['tl_news']['schema_speakable'] = ['Speakable CSS-Selektoren', 'Kommagetrennt. Markiert vorlesbare Bereiche für Sprach-/KI-Assistenten.'];
$GLOBALS['TL_LANG']['tl_news']['schema_customJsonLd'] = ['Eigenes JSON-LD', 'Zusätzliche Knoten für den @graph (ohne @context).'];
