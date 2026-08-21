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

namespace VTinnovations\SchemaOrg\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use VTinnovations\SchemaOrg\Remote\UsageSignal;
use VTinnovations\SchemaOrg\Schema\SchemaBuilder;
use VTinnovations\SchemaOrg\Site\HostName;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;

/**
 * Injects the JSON-LD block straight before </head> on kernel.response.
 *
 * Injection is deliberately NOT done via TL_HEAD: custom fe_page templates
 * (page builders, award themes, etc.) frequently drop the TL_HEAD insert tag,
 * which would silently swallow the markup. Rewriting the response body is the
 * one place guaranteed to survive any template.
 *
 * This is one of the boundaries where the licence actually decides something.
 * It checks the evaluated state and, separately, that the host being served is
 * itself one of the licensed hosts — a site can answer on more names than it is
 * licensed for, and those requests must come out as plain Contao.
 */
#[AsEventListener(priority: -768)]
final class SchemaResponseListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly SchemaBuilder $builder,
        private readonly StatusEvaluator $evaluator,
        private readonly UsageSignal $signal,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        $page = $request->attributes->get('pageModel');

        if (!$page instanceof PageModel) {
            return;
        }

        $response = $event->getResponse();

        $contentType = $response->headers->get('Content-Type', '');
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (!\is_string($content) || !str_contains($content, '</head>')) {
            return;
        }

        // Verify the stored package only once this is a page we would write to.
        $status = $this->evaluator->current();

        if (!$status->isEntitled()) {
            return;
        }

        $host = HostName::tryFrom($request->getHost());

        if (null === $host || !$status->authorises($host)) {
            return;
        }

        $script = $this->builder->buildFor($page, $request)->toScript();
        if ($script === '') {
            return;
        }

        $pos = strpos($content, '</head>');
        $response->setContent(substr($content, 0, (int) $pos) . $script . substr($content, (int) $pos));

        // The licensed feature ran; the signal goes out after the response and
        // carries only the product and the host.
        $this->signal->queueInvocation($host);
    }
}
