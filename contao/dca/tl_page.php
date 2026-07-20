<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 *
 * Adds the site-wide schema settings to root pages and per-page schema
 * overrides to every other page type.
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * ── Fields ──────────────────────────────────────────────────────────────
 */
$GLOBALS['TL_DCA']['tl_page']['fields'] += [
    // Root: site-wide -------------------------------------------------------
    'schema_disable' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 clr'],
        'sql' => "char(1) NOT NULL default ''",
    ],
    'schema_orgType' => [
        'inputType' => 'select',
        'options' => ['Organization', 'LocalBusiness', 'none'],
        'reference' => &$GLOBALS['TL_LANG']['tl_page']['schema_orgTypeOptions'],
        'eval' => ['tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default 'Organization'",
    ],
    'schema_orgName' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_orgLogo' => [
        'inputType' => 'fileTree',
        'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'extensions' => 'jpg,jpeg,png,gif,svg,webp', 'tl_class' => 'clr'],
        'sql' => 'binary(16) NULL',
    ],
    'schema_orgSameAs' => [
        'inputType' => 'listWizard',
        'eval' => ['tl_class' => 'clr'],
        'sql' => 'blob NULL',
    ],
    'schema_orgPhone' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
        'sql' => "varchar(64) NOT NULL default ''",
    ],
    'schema_orgEmail' => [
        'inputType' => 'text',
        'eval' => ['rgxp' => 'email', 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_orgStreet' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_orgPostal' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_orgCity' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'schema_orgRegion' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'schema_orgCountry' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 2, 'tl_class' => 'w50', 'placeholder' => 'DE'],
        'sql' => "varchar(2) NOT NULL default ''",
    ],
    'schema_geoLat' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_geoLng' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_openingHours' => [
        'inputType' => 'listWizard',
        'eval' => ['tl_class' => 'clr', 'placeholder' => 'Mo-Fr 09:00-18:00'],
        'sql' => 'blob NULL',
    ],
    'schema_priceRange' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 32, 'tl_class' => 'w50', 'placeholder' => '€€'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_website' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 clr'],
        'sql' => "char(1) NOT NULL default '1'",
    ],
    'schema_searchUrl' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'clr', 'placeholder' => 'https://example.com/suche.html?keywords={search_term_string}'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],

    // Non-root: per-page overrides -----------------------------------------
    'schema_pageDisable' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 clr'],
        'sql' => "char(1) NOT NULL default ''",
    ],
    'schema_webPageType' => [
        'inputType' => 'select',
        'options' => ['WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'ProfilePage', 'FAQPage', 'QAPage', 'ItemPage', 'SearchResultsPage', 'CheckoutPage'],
        'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_speakable' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'clr', 'placeholder' => 'h1, .intro'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_customJsonLd' => [
        'inputType' => 'textarea',
        'eval' => ['rows' => 6, 'preserveTags' => true, 'decodeEntities' => false, 'class' => 'monospace', 'tl_class' => 'clr'],
        'sql' => 'text NULL',
    ],
];

/*
 * ── Palettes ────────────────────────────────────────────────────────────
 */
$rootFields = [
    'schema_disable', 'schema_orgType', 'schema_orgName', 'schema_orgLogo',
    'schema_orgSameAs', 'schema_orgPhone', 'schema_orgEmail',
    'schema_orgStreet', 'schema_orgPostal', 'schema_orgCity', 'schema_orgRegion', 'schema_orgCountry',
    'schema_geoLat', 'schema_geoLng', 'schema_openingHours', 'schema_priceRange',
    'schema_website', 'schema_searchUrl',
];

$rootPalette = PaletteManipulator::create()
    ->addLegend('schema_legend', 'global_legend', PaletteManipulator::POSITION_AFTER, true);

foreach ($rootFields as $field) {
    $rootPalette->addField($field, 'schema_legend', PaletteManipulator::POSITION_APPEND);
}

foreach (['root', 'rootfallback'] as $palette) {
    if (isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
        $rootPalette->applyToPalette($palette, 'tl_page');
    }
}

// Per-page overrides on every non-root palette.
$overrideFields = ['schema_pageDisable', 'schema_webPageType', 'schema_speakable', 'schema_customJsonLd'];

$overridePalette = PaletteManipulator::create()
    ->addLegend('schema_legend', 'expert_legend', PaletteManipulator::POSITION_BEFORE, true);

foreach ($overrideFields as $field) {
    $overridePalette->addField($field, 'schema_legend', PaletteManipulator::POSITION_APPEND);
}

foreach (array_keys($GLOBALS['TL_DCA']['tl_page']['palettes']) as $palette) {
    if (\in_array($palette, ['__selector__', 'root', 'rootfallback'], true)) {
        continue;
    }
    if (!\is_string($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
        continue;
    }
    $overridePalette->applyToPalette($palette, 'tl_page');
}
