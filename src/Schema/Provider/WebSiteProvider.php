<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Schema\Provider;

use VTinnovations\SchemaOrg\Config\SchemaConfig;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * WebSite node with an optional SearchAction (Google sitelinks searchbox /
 * a machine-readable search entry point for AI crawlers).
 */
final class WebSiteProvider implements NodeProviderInterface
{
    public function __construct(private readonly SchemaConfig $config)
    {
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $root = $ctx->rootPage;

        if (!$this->config->showsWebSite($root)) {
            return;
        }

        $name = $this->config->organizationName($root);
        if ($name === '') {
            $name = trim((string) $root->title);
        }

        $node = [
            '@type' => 'WebSite',
            '@id' => $ctx->webSiteId(),
            'url' => $ctx->baseUrl . '/',
            'name' => $name,
            'inLanguage' => $ctx->language(),
        ];

        if ($this->config->showsOrganization($root)) {
            $node['publisher'] = $graph->ref($ctx->organizationId());
        }

        $searchTemplate = $this->config->searchUrlTemplate($root);
        if ($searchTemplate !== '') {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        $graph->add($node);
    }
}
