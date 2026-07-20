<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

if (!isset($GLOBALS['TL_DCA']['tl_calendar_events'])) {
    return;
}

$GLOBALS['TL_DCA']['tl_calendar_events']['fields'] += [
    'schema_disable' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 clr'],
        'sql' => "char(1) NOT NULL default ''",
    ],
    'schema_eventStatus' => [
        'inputType' => 'select',
        'options' => ['EventScheduled', 'EventRescheduled', 'EventPostponed', 'EventMovedOnline', 'EventCancelled'],
        'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventStatusOptions'],
        'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
        'sql' => "varchar(32) NOT NULL default ''",
    ],
    'schema_eventAttendance' => [
        'inputType' => 'select',
        'options' => ['offline', 'online', 'mixed'],
        'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventAttendanceOptions'],
        'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
        'sql' => "varchar(16) NOT NULL default ''",
    ],
    'schema_location' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
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
    ->addField('schema_eventStatus', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_eventAttendance', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_location', 'schema_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('schema_customJsonLd', 'schema_legend', PaletteManipulator::POSITION_APPEND);

if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'])) {
    $pm->applyToPalette('default', 'tl_calendar_events');
}
