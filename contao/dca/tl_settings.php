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

/**
 * Adds the licence section to Contao → Settings. This is the only place in the
 * extension where licence state can be viewed or changed; there is no separate
 * module, no root page panel and no second route.
 * The section is rendered by a service and its buttons post back to this same
 * screen, where the onload callback picks them up before Contao builds the
 * form. Field names carry the package prefix so several V&T packages can add
 * their own section without colliding.
 *
 * The legend is prepended and shared: every V&T package adds its field to the
 * same "vtone_licence_legend" group, so all licence sections sit together in
 * one fieldset at the top of the Settings screen, above Contao's own legends,
 * with the package name shown as the field's own heading instead of a
 * separate per-package legend.
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\System;
use VTinnovations\SchemaOrg\DataContainer\SettingsPanel;

$GLOBALS['TL_DCA']['tl_settings']['fields']['vts_schemaorg_panel'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['vts_schemaorg_panel'],
    'input_field_callback' => static fn (DataContainer $dc, string $xlabel = ''): string => System::getContainer()
        ->get(SettingsPanel::class)
        ->render($dc, $xlabel),
];

$GLOBALS['TL_DCA']['tl_settings']['config']['onload_callback'][] = static function (DataContainer $dc): void {
    System::getContainer()->get(SettingsPanel::class)->handle($dc);
};

PaletteManipulator::create()
    ->addLegend('vtone_licence_legend', null, PaletteManipulator::POSITION_PREPEND)
    ->addField('vts_schemaorg_panel', 'vtone_licence_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');
