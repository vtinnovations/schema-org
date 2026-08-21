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

namespace VTinnovations\SchemaOrg\Schema;

/**
 * A contributor of schema.org nodes. Each implementation inspects the request
 * context and adds zero or more nodes to the shared graph. Providers are
 * tagged 'vtinnovations_schema.node_provider'; higher getPriority() runs first
 * so foundational nodes (Organization, WebSite) exist before dependants link
 * to them.
 */
interface NodeProviderInterface
{
    public function contribute(SchemaContext $context, SchemaGraph $graph): void;

    /**
     * Higher runs earlier. Foundational nodes use high values; page/detail
     * nodes that reference them use lower values.
     */
    public function getPriority(): int;
}
