<?php

declare(strict_types=1);

use VTinnovations\SchemaOrg\Controller\PreviewController;

/*
 * Backend module group "Schema.org" — a read-only preview of the JSON-LD that
 * the front end response listener injects, with one-click validator links.
 * The actual configuration lives on the page tree (root page = site-wide
 * settings, other pages/news/events/faq = per-record overrides).
 */
$GLOBALS['BE_MOD']['schema_org'] = [
    'schema' => [
        'callback' => PreviewController::class,
    ],
];
