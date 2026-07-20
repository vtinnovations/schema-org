<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class VtinnovationsSchemaOrgExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Scratch dir holds only the cached license state (var/schema-org/license.json).
        $projectDir = (string) $container->getParameter('kernel.project_dir');
        $container->setParameter('vtinnovations_schema.scratch_dir', $projectDir . '/var/schema-org');

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 2) . '/config'),
        );

        $loader->load('services.yaml');
    }
}
