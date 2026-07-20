<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use VTinnovations\SchemaOrg\Schema\SchemaBuilder;
use VTinnovations\SchemaOrg\Security\LicenseGuard;

/**
 * Injects the JSON-LD block straight before </head> on kernel.response.
 *
 * Injection is deliberately NOT done via TL_HEAD: custom fe_page templates
 * (page builders, award themes, etc.) frequently drop the TL_HEAD insert tag,
 * which would silently swallow the markup. Rewriting the response body is the
 * one place guaranteed to survive any template.
 */
#[AsEventListener(priority: -768)]
final class SchemaResponseListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly SchemaBuilder $builder,
        private readonly LicenseGuard $licenseGuard,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Paid feature: without a valid license no structured data is emitted.
        // Reads the cached license state only — never makes an HTTP call here.
        if (!$this->licenseGuard->isLicensed()) {
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

        $script = $this->builder->buildFor($page, $request)->toScript();
        if ($script === '') {
            return;
        }

        $pos = strpos($content, '</head>');
        $response->setContent(substr($content, 0, (int) $pos) . $script . substr($content, (int) $pos));
    }
}
