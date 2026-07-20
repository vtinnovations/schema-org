<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
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
