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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;

/**
 * Turns a resolved front end page into a cross-linked schema.org @graph by
 * running every tagged {@see NodeProviderInterface} against a shared graph.
 *
 * The graph is the licensed output of this extension, so the decision is
 * enforced here as well as at the response listener. Anything calling this
 * service directly — the backend preview, another bundle, a command — gets the
 * same empty graph when the installation is not authorised.
 */
final class SchemaBuilder
{
    /** @var list<NodeProviderInterface> sorted by priority, high first */
    private readonly array $providers;

    /**
     * @param iterable<NodeProviderInterface> $providers
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly StatusEvaluator $evaluator,
        iterable $providers,
    ) {
        $providers = $providers instanceof \Traversable ? iterator_to_array($providers) : $providers;
        usort($providers, static fn (NodeProviderInterface $a, NodeProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());
        $this->providers = array_values($providers);
    }

    public function buildFor(PageModel $page, Request $request): SchemaGraph
    {
        $graph = new SchemaGraph();

        if (!$this->evaluator->current()->isEntitled()) {
            return $graph;
        }

        $context = $this->createContext($page, $request);
        if ($context === null) {
            return $graph;
        }

        foreach ($this->providers as $provider) {
            try {
                $provider->contribute($context, $graph);
            } catch (\Throwable) {
                // A single misbehaving provider must never break the page.
                continue;
            }
        }

        return $graph;
    }

    private function createContext(PageModel $page, Request $request): ?SchemaContext
    {
        try {
            $page->loadDetails();
        } catch (\Throwable) {
            // continue with whatever the model already holds
        }

        $rootPage = $this->resolveRoot($page);
        if ($rootPage === null) {
            return null;
        }

        // Kill switch: whole site off, or this single page opted out.
        if ((bool) $rootPage->schema_disable || (bool) $page->schema_pageDisable) {
            return null;
        }

        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $pageUrl = $baseUrl . $request->getPathInfo();

        return new SchemaContext(
            page: $page,
            rootPage: $rootPage,
            baseUrl: $baseUrl,
            pageUrl: $pageUrl,
            autoItem: $this->autoItem(),
        );
    }

    private function resolveRoot(PageModel $page): ?PageModel
    {
        if ($page->type === 'root') {
            return $page;
        }

        $rootId = (int) $page->rootId;
        if ($rootId <= 0) {
            return null;
        }

        /** @var PageModel $adapter */
        $adapter = $this->framework->getAdapter(PageModel::class);
        $root = $adapter->findByPk($rootId);

        return $root instanceof PageModel ? $root : null;
    }

    private function autoItem(): ?string
    {
        $this->framework->initialize();

        /** @var Input $input */
        $input = $this->framework->getAdapter(Input::class);
        $value = $input->get('auto_item');

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
