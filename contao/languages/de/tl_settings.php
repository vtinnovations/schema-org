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

// Gemeinsame Überschrift der Lizenz-Legende, der jedes V-T.ONE-Paket ein Feld hinzufügt.
$GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] = 'V-T.ONE Licence management';

// Feldbezeichnung. Wird als eigene Überschrift dieses Abschnitts oberhalb
// seiner Ausgabe angezeigt und unterscheidet ihn von den Geschwistern in der gemeinsamen Legende.
$GLOBALS['TL_LANG']['tl_settings']['vts_schemaorg_panel'] = [
    'Schema.org',
    'Lizenz für diese Installation aktivieren, aktualisieren oder entfernen.',
];

$GLOBALS['TL_LANG']['tl_settings']['schema-org_licence'] = [
    'state_active' => 'Lifetime-Free-Lizenz aktiv. Alle Funktionen freigeschaltet.',
    'state_absent' => 'Keine Lizenz aktiviert. Es werden keine strukturierten Daten ausgegeben, bis eine Lizenz aktiviert ist.',
    'state_refused' => 'Die gespeicherte Lizenz gilt nicht für diese Installation. Es werden keine strukturierten Daten ausgegeben.',
    'key_label' => 'Lizenzschlüssel',
    'key_placeholder' => 'XXXXX-XXXXX-XXXXX-XXXXX',
    'key_help' => 'Schlüssel eingeben und aktivieren. Bereits lizenziert? Nach einer Verlängerung oder einem Domainwechsel genügt „Lizenz aktualisieren“ — der Schlüssel muss nicht erneut eingegeben werden.',
    'btn_activate' => 'Lizenz prüfen und aktivieren',
    'btn_refresh' => 'Lizenz aktualisieren',
    'btn_remove' => 'Lizenz entfernen',
    'btn_activate_adopted' => 'Gefundenen Schlüssel der Vorversion aktivieren',
    'confirm_remove' => 'Lizenz von dieser Installation entfernen? Die Erweiterung gibt danach keine strukturierten Daten mehr aus.',
    'adopted_note' => 'Es wurde ein Lizenzschlüssel aus einer früheren Version dieser Erweiterung gefunden. Lizenzen sind jetzt signiert, der Schlüssel muss deshalb einmal neu aktiviert werden.',
    'field_key' => 'Schlüssel',
    'field_tier' => 'Paket',
    'field_from' => 'Gültig ab',
    'field_until' => 'Gültig bis',
    'field_unlimited' => 'unbegrenzt',
    'field_checked' => 'Zuletzt geprüft',
    'no_domain' => 'Auf keiner Startseite ist eine Domain hinterlegt, daher kann keine Lizenz aktiviert werden. Bitte zuerst die Domain der Startseite setzen.',
    'ok_activate' => 'Die Lizenz wurde aktiviert.',
    'ok_refresh' => 'Die Lizenz wurde aktualisiert.',
    'ok_remove' => 'Die Lizenz wurde entfernt. Die Erweiterung gibt keine strukturierten Daten mehr aus.',
    'err_generic' => 'Die Lizenz konnte nicht aktiviert werden. Bitte den Schlüssel prüfen und erneut versuchen.',
    'err_refresh' => 'Die Lizenz konnte nicht aktualisiert werden. Die bisherige Lizenz bleibt bestehen.',
    'err_remove' => 'Die Lizenz konnte nicht entfernt werden.',
    'err_confirm' => 'Das Entfernen wurde nicht bestätigt, es wurde nichts geändert.',
    'err_no_state' => 'Auf dieser Installation ist noch keine Lizenz gespeichert, es gibt also nichts zu aktualisieren oder zu entfernen. Bitte zuerst einen Schlüssel eingeben und aktivieren.',
    'err_unavailable' => 'Der Lizenzserver war nicht erreichbar. Es wurde nichts geändert.',
    'err_host' => 'Diese Lizenz ist für keine der auf dieser Installation konfigurierten Domains ausgestellt.',
];
