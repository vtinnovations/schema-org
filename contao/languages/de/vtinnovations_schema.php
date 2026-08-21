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
 * Texte für das Backend-Modul „Schema.org“.
 *
 * Wird bei Bedarf mit System::loadLanguageFile('vtinnovations_schema') geladen,
 * da Contao nur default.php und modules.php automatisch lädt.
 */
$GLOBALS['TL_LANG']['vtinnovations_schema'] = [
    'title' => 'Schema.org',
    'subtitle' => 'Strukturierte Daten (JSON-LD) für Suchmaschinen und KI-Antwortmaschinen.',
    'locked' => 'Für diese Installation ist keine gültige Lizenz aktiv, daher werden keine strukturierten Daten ausgegeben.',
    'locked_link' => 'Lizenz in den Einstellungen verwalten',
    'active' => 'Lizenz aktiv',
    'preview_h' => 'JSON-LD-Vorschau',
    'preview_d' => 'Zeigt die für eine Seite generierten strukturierten Daten und verlinkt zu den Validatoren.',
    'preview_page' => 'Seite',
    'preview_choose' => '— bitte wählen —',
    'preview_show' => 'Anzeigen',
    'url_label' => 'URL',
    'btn_rich' => 'Google Rich Results Test',
    'btn_validator' => 'schema.org-Validator',
    'err_notfound' => 'Seite nicht gefunden.',
    'err_nourl' => 'Für diesen Seitentyp lässt sich keine URL bilden.',
    'empty' => 'Für diese Seite wird kein Schema ausgegeben (deaktiviert oder keine Daten konfiguriert).',
];
