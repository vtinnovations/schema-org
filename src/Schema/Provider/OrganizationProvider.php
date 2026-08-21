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

namespace VTinnovations\SchemaOrg\Schema\Provider;

use VTinnovations\SchemaOrg\Config\SchemaConfig;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * Site-wide Organization / LocalBusiness node — the entity every other node
 * points back to via publisher/about. Configured on the root page.
 */
final class OrganizationProvider implements NodeProviderInterface
{
    public function __construct(private readonly SchemaConfig $config)
    {
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        $root = $ctx->rootPage;

        if (!$this->config->showsOrganization($root)) {
            return;
        }

        $name = $this->config->organizationName($root);
        if ($name === '') {
            return;
        }

        $type = $this->config->organizationType($root);

        $node = [
            '@type' => $type,
            '@id' => $ctx->organizationId(),
            'name' => $name,
            'url' => $ctx->baseUrl . '/',
        ];

        $logo = $this->config->logoUrl($root, $ctx->baseUrl);
        if ($logo !== '') {
            $node['logo'] = [
                '@type' => 'ImageObject',
                '@id' => $ctx->baseUrl . '/#logo',
                'url' => $logo,
                'contentUrl' => $logo,
            ];
            $node['image'] = $graph->ref($ctx->baseUrl . '/#logo');
        }

        $sameAs = $this->config->sameAs($root);
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        $phone = $this->config->telephone($root);
        $email = $this->config->email($root);
        if ($phone !== '') {
            $node['telephone'] = $phone;
        }
        if ($email !== '') {
            $node['email'] = $email;
        }

        $address = $this->config->postalAddress($root);
        if ($address !== null) {
            $node['address'] = $address;
        }

        if ($phone !== '' || $email !== '') {
            $contactPoint = ['@type' => 'ContactPoint', 'contactType' => 'customer service'];
            if ($phone !== '') {
                $contactPoint['telephone'] = $phone;
            }
            if ($email !== '') {
                $contactPoint['email'] = $email;
            }
            $node['contactPoint'] = $contactPoint;
        }

        // LocalBusiness-only enrichments.
        if ($type !== 'Organization') {
            $geo = $this->config->geo($root);
            if ($geo !== null) {
                $node['geo'] = $geo;
            }

            $hours = $this->config->openingHours($root);
            if ($hours !== []) {
                $node['openingHours'] = $hours;
            }

            $priceRange = $this->config->priceRange($root);
            if ($priceRange !== '') {
                $node['priceRange'] = $priceRange;
            }
        }

        $graph->add($node);
    }
}
