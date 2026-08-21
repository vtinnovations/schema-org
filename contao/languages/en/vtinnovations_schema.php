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
 * Strings for the "Schema.org" backend module.
 *
 * Loaded on demand with System::loadLanguageFile('vtinnovations_schema'),
 * because Contao only auto-loads default.php and modules.php.
 */
$GLOBALS['TL_LANG']['vtinnovations_schema'] = [
    'title' => 'Schema.org',
    'subtitle' => 'Structured data (JSON-LD) for search engines and AI answer engines.',
    'locked' => 'No valid licence is active for this installation, so no structured data is emitted.',
    'locked_link' => 'Manage the licence in the settings',
    'active' => 'Licence active',
    'preview_h' => 'JSON-LD preview',
    'preview_d' => 'Shows the structured data generated for a page and links to the validators.',
    'preview_page' => 'Page',
    'preview_choose' => '— please choose —',
    'preview_show' => 'Show',
    'url_label' => 'URL',
    'btn_rich' => 'Google Rich Results Test',
    'btn_validator' => 'schema.org validator',
    'err_notfound' => 'Page not found.',
    'err_nourl' => 'No URL can be built for this page type.',
    'empty' => 'No schema is emitted for this page (disabled or nothing configured).',
];
