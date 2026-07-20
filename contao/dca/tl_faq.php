<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

if (!isset($GLOBALS['TL_DCA']['tl_faq'])) {
    return;
}

$GLOBALS['TL_DCA']['tl_faq']['fields']['schema_disable'] = [
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 clr'],
    'sql' => "char(1) NOT NULL default ''",
];

if (isset($GLOBALS['TL_DCA']['tl_faq']['palettes']['default'])) {
    PaletteManipulator::create()
        ->addLegend('schema_legend', 'expert_legend', PaletteManipulator::POSITION_BEFORE, true)
        ->addField('schema_disable', 'schema_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette('default', 'tl_faq');
}
