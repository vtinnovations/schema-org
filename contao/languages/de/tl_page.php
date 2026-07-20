<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_page']['schema_legend'] = 'Schema.org / Strukturierte Daten';

$GLOBALS['TL_LANG']['tl_page']['schema_orgTypeOptions'] = [
    'Organization' => 'Organisation',
    'LocalBusiness' => 'Lokales Unternehmen (mit Adresse/Öffnungszeiten)',
    'none' => 'Keine Organisation ausgeben',
];

$labels = [
    // Root: site-wide
    'schema_disable' => ['Schema.org komplett deaktivieren', 'Gibt für diese gesamte Website kein JSON-LD aus.'],
    'schema_orgType' => ['Organisationstyp', 'Bestimmt den Typ des zentralen Entitäts-Knotens.'],
    'schema_orgName' => ['Name der Organisation', 'Leer = Titel der Startseite wird verwendet.'],
    'schema_orgLogo' => ['Logo', 'Wird als logo/image der Organisation ausgegeben (ImageObject).'],
    'schema_orgSameAs' => ['sameAs (Profil-URLs)', 'Social-Media-/Wikipedia-/Profil-URLs, je eine pro Zeile.'],
    'schema_orgPhone' => ['Telefon', 'Wird als telephone und ContactPoint ausgegeben.'],
    'schema_orgEmail' => ['E-Mail', 'Wird als email und ContactPoint ausgegeben.'],
    'schema_orgStreet' => ['Straße und Hausnummer', ''],
    'schema_orgPostal' => ['PLZ', ''],
    'schema_orgCity' => ['Ort', ''],
    'schema_orgRegion' => ['Region/Bundesland', ''],
    'schema_orgCountry' => ['Ländercode', 'Zweistelliger ISO-Code, z. B. DE.'],
    'schema_geoLat' => ['Breitengrad (Latitude)', 'Nur für lokales Unternehmen.'],
    'schema_geoLng' => ['Längengrad (Longitude)', 'Nur für lokales Unternehmen.'],
    'schema_openingHours' => ['Öffnungszeiten', 'Je Zeile ein Eintrag im Format „Mo-Fr 09:00-18:00“.'],
    'schema_priceRange' => ['Preisniveau', 'z. B. €, €€, €€€. Nur für lokales Unternehmen.'],
    'schema_website' => ['WebSite-Knoten ausgeben', 'Aktiviert den WebSite-Knoten (nötig für die Suchbox).'],
    'schema_searchUrl' => ['Such-URL (SearchAction)', 'URL der Suchergebnisseite mit {search_term_string} als Platzhalter. Leer = keine Suchbox.'],
    // Non-root: overrides
    'schema_pageDisable' => ['Schema für diese Seite deaktivieren', 'Unterdrückt jegliches JSON-LD auf dieser Seite.'],
    'schema_webPageType' => ['WebPage-Typ', 'Genauerer Seitentyp für Suchmaschinen/KI.'],
    'schema_speakable' => ['Speakable CSS-Selektoren', 'Kommagetrennt. Markiert vorlesbare Bereiche für Sprach-/KI-Assistenten.'],
    'schema_customJsonLd' => ['Eigenes JSON-LD', 'Wird zusätzlich in den @graph eingefügt (ohne @context). Ein Objekt oder ein Array von Objekten.'],
];

foreach ($labels as $field => $label) {
    $GLOBALS['TL_LANG']['tl_page'][$field] = $label;
}
