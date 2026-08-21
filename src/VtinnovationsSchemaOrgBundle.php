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

namespace VTinnovations\SchemaOrg;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class VtinnovationsSchemaOrgBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
