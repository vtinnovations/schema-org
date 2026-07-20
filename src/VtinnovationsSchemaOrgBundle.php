<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class VtinnovationsSchemaOrgBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
