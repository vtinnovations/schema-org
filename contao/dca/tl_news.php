<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

if (!isset($GLOBALS['TL_DCA']['tl_news'])) {
    return;
}

$GLOBALS['TL_DCA']['tl_news']['fields'] += [
    'schema_disable' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 clr'],
        'sql' => "char(1) NOT NULL default ''",
    ],
    'schema_articleType' => [
        'inputType' => 'select',
        'options' => ['NewsArticle', 'Article', 'BlogPosting', 'ReportageNewsArticle', 'OpinionNewsArticle'],
        'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_authorName' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
        'sql' => "varchar(128) NOT NULL default ''",
    ],
    'schema_authorUrl' => [
        'inputType' => 'text',
        'eval' => ['rgxp' => 'url', 'maxlength' => 255, 'tl_class' => 'w50'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_speakable' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'clr', 'placeholder' => 'h1, .ce_text'],
        'sql' => "varchar(255) NOT NULL default ''",
    ],
    'schema_customJsonLd' => [
        'inputType' => 'textarea',
        'eval' => ['rows' => 5, 'preserveTags' => true, 'decodeEntities' => false, 'class' => 'monospace', 'tl_class' => 'clr'],
        'sql' => 'text NULL',
    ],
];

$pm = PaletteManipulator::create()
    ->addLegend('schema_legend', 'expert_legend', PaletteManipulator::POSITION_BEFORE, true)
    ->addField('schema_disable', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_articleType', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_authorName', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_authorUrl', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_speakable', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_customJsonLd', 'schema_legend', PaletteManipulator::POSITION_APPEND);

if (isset($GLOBALS['TL_DCA']['tl_news']['palettes']['default'])) {
    $pm->applyToPalette('default', 'tl_news');
}
