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

// Shared headline for the licence legend every V-T.ONE package adds a field to.
$GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] = 'V-T.ONE Licence management';

// Field label. Rendered as this section's own heading above its markup, so it
// is what tells this package's section apart from its siblings in the shared legend.
$GLOBALS['TL_LANG']['tl_settings']['vts_schemaorg_panel'] = [
    'Schema.org',
    'Activate, update or remove the licence for this installation.',
];

$GLOBALS['TL_LANG']['tl_settings']['schema-org_licence'] = [
    'state_active' => 'Lifetime Free licence active. All features unlocked.',
    'state_absent' => 'No licence activated. Structured data output stays switched off until a licence is activated.',
    'state_refused' => 'The stored licence does not authorise this installation. Structured data output stays switched off.',
    'key_label' => 'Licence key',
    'key_placeholder' => 'XXXXX-XXXXX-XXXXX-XXXXX',
    'key_help' => 'Enter your key and activate. Already licensed? Use "Update Licence" after a renewal or a domain change — no need to type the key again.',
    'btn_activate' => 'Verify and Activate Licence',
    'btn_refresh' => 'Update Licence',
    'btn_remove' => 'Remove Licence',
    'btn_activate_adopted' => 'Activate the key found from the previous version',
    'confirm_remove' => 'Remove the licence from this installation? The extension will stop outputting structured data.',
    'adopted_note' => 'A licence key from a previous version of this extension was found. Licences are now signed, so the key has to be activated once more.',
    'field_key' => 'Key',
    'field_tier' => 'Package',
    'field_from' => 'Valid from',
    'field_until' => 'Valid until',
    'field_unlimited' => 'unlimited',
    'field_checked' => 'Last verified',
    'no_domain' => 'No domain is configured on any root page, so no licence can be activated. Set the domain on the root page first.',
    'ok_activate' => 'The licence was activated.',
    'ok_refresh' => 'The licence was updated.',
    'ok_remove' => 'The licence was removed. The extension no longer outputs structured data.',
    'err_generic' => 'The licence could not be activated. Please check the key and try again.',
    'err_refresh' => 'The licence could not be updated. The previous licence remains in place.',
    'err_remove' => 'The licence could not be removed.',
    'err_confirm' => 'The removal was not confirmed, so nothing was changed.',
    'err_no_state' => 'No licence is stored on this installation yet, so there is nothing to update or remove. Enter a key and activate it first.',
    'err_unavailable' => 'The licence server could not be reached. Nothing was changed.',
    'err_host' => 'This licence is not issued for any domain configured on this installation.',
];
