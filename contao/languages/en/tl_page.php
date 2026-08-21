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

$GLOBALS['TL_LANG']['tl_page']['schema_legend'] = 'Schema.org / Structured data';

$GLOBALS['TL_LANG']['tl_page']['schema_orgTypeOptions'] = [
    'Organization' => 'Organization',
    'LocalBusiness' => 'Local business (address/opening hours)',
    'none' => 'Do not output an organization',
];

$GLOBALS['TL_LANG']['tl_page']['schema_webPageTypeOptions'] = [
    'WebPage' => 'Web page (generic)',
    'AboutPage' => 'About page',
    'ContactPage' => 'Contact page',
    'CollectionPage' => 'Collection page (overview/list)',
    'ProfilePage' => 'Profile page',
    'FAQPage' => 'FAQ page',
    'QAPage' => 'Question and answer page',
    'ItemPage' => 'Item page (single object)',
    'SearchResultsPage' => 'Search results page',
    'CheckoutPage' => 'Checkout page',
];

$labels = [
    // Root: site-wide
    'schema_disable' => ['Disable Schema.org completely', 'Outputs no JSON-LD for this entire website.'],
    'schema_orgType' => ['Organization type', 'Type of the central entity node.'],
    'schema_orgName' => ['Organization name', 'Empty = the start page title is used.'],
    'schema_orgLogo' => ['Logo', 'Emitted as the organization logo/image (ImageObject).'],
    'schema_orgSameAs' => ['sameAs (profile URLs)', 'Social/Wikipedia/profile URLs, one per line.'],
    'schema_orgPhone' => ['Telephone', 'Emitted as telephone and ContactPoint.'],
    'schema_orgEmail' => ['Email', 'Emitted as email and ContactPoint.'],
    'schema_orgStreet' => ['Street address', 'Street and house number of the postal address.'],
    'schema_orgPostal' => ['Postal code', 'Postal code of the postal address.'],
    'schema_orgCity' => ['City', 'Town or city of the postal address.'],
    'schema_orgRegion' => ['Region/state', 'Region, state or province of the postal address.'],
    'schema_orgCountry' => ['Country code', 'Two-letter ISO code, e.g. DE.'],
    'schema_geoLat' => ['Latitude', 'Local business only.'],
    'schema_geoLng' => ['Longitude', 'Local business only.'],
    'schema_openingHours' => ['Opening hours', 'One entry per line, e.g. “Mo-Fr 09:00-18:00”.'],
    'schema_priceRange' => ['Price range', 'e.g. €, €€, €€€. Local business only.'],
    'schema_website' => ['Output WebSite node', 'Enables the WebSite node (required for the search box).'],
    'schema_searchUrl' => ['Search URL (SearchAction)', 'Search results URL with {search_term_string} placeholder. Empty = no search box.'],
    // Non-root: overrides
    'schema_pageDisable' => ['Disable schema for this page', 'Suppresses all JSON-LD on this page.'],
    'schema_webPageType' => ['WebPage type', 'More specific page type for search engines/AI.'],
    'schema_speakable' => ['Speakable CSS selectors', 'Comma-separated. Marks read-aloud regions for voice/AI assistants.'],
    'schema_customJsonLd' => ['Custom JSON-LD', 'Added to the @graph (without @context). One object or an array of objects.'],
];

foreach ($labels as $field => $label) {
    $GLOBALS['TL_LANG']['tl_page'][$field] = $label;
}
