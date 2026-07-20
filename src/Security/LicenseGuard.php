<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Security;

/**
 * Single-gate helper injected where the paid feature is guarded (the front end
 * JSON-LD listener and the backend module). Paid-only product, so there is one
 * gate: isLicensed().
 */
final class LicenseGuard
{
    public function __construct(private readonly LicenseManager $licenseManager)
    {
    }

    public function isLicensed(): bool
    {
        return $this->licenseManager->isLicensed();
    }
}
