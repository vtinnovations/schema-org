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

namespace VTinnovations\SchemaOrg\Tests;

use PHPUnit\Framework\TestCase;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\DataContainer\SettingsPanel;
use VTinnovations\SchemaOrg\Tests\Support\SourceText;

/**
 * The static half of the control-surface trace.
 *
 * A rendered button proves nothing on its own, so this walks each control back
 * to a registered callback, a permission check, a token check and a handler.
 * The other half — clicking them in a running Contao — needs a Contao runtime,
 * which this environment does not have; those cases are reported as not run
 * rather than assumed to pass.
 */
final class ContaoWiringTest extends TestCase
{
    private string $panel;

    private string $dca;

    private string $services;

    private string $routes;

    protected function setUp(): void
    {
        $this->panel = $this->read('src/DataContainer/SettingsPanel.php');
        $this->dca = $this->read('contao/dca/tl_settings.php');
        $this->services = $this->read('config/services.yaml');
        $this->routes = $this->read('config/routes.yaml');
    }

    // ── the section exists and is the only one ───────────────────────────

    public function testTheSectionIsRegisteredOnTheSettingsScreen(): void
    {
        self::assertStringContainsString("\$GLOBALS['TL_DCA']['tl_settings']['fields']['vts_schemaorg_panel']", $this->dca);
        self::assertStringContainsString('input_field_callback', $this->dca);
        self::assertStringContainsString("'onload_callback'", $this->dca);
        self::assertStringContainsString("applyToPalette('default', 'tl_settings')", $this->dca);
        self::assertStringContainsString('SettingsPanel::class', $this->dca);
    }

    public function testTheHeadlineIsExactInEveryLanguage(): void
    {
        foreach (['en', 'de'] as $language) {
            $file = $this->read('contao/languages/' . $language . '/tl_settings.php');

            // The legend is shared with every other V-T.ONE package; the field label is what
            // identifies this section within it.
            self::assertStringContainsString(
                "\$GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] = 'V-T.ONE Licence management';",
                $file,
                $language,
            );
            self::assertStringContainsString("'Schema.org',", $file, $language);
        }

        self::assertSame('Schema.org', (new ProductProfile())->title());
    }

    public function testThereIsNoSecondSurface(): void
    {
        // No standalone module, no root page panel, no duplicate DCA section.
        // Comments are stripped first: every file's header names the licence.
        $config = SourceText::withoutComments($this->read('contao/config/config.php'));
        self::assertStringNotContainsString('licen', strtolower($config));

        $page = SourceText::withoutComments($this->read('contao/dca/tl_page.php'));
        self::assertStringNotContainsString('licen', strtolower($page));

        $preview = $this->read('src/Controller/PreviewController.php');
        self::assertStringNotContainsString('<input type="text" name="license', $preview);
        self::assertStringNotContainsString('save_license', $preview);
        self::assertStringNotContainsString('method="post"', $preview, 'the preview module posts nothing');
    }

    // ── each control resolves to a handler ───────────────────────────────

    public function testEveryRenderedControlHasAHandlerBranch(): void
    {
        $actions = [
            SettingsPanel::ACTIVATE,
            SettingsPanel::ACTIVATE_ADOPTED,
            SettingsPanel::REFRESH,
            SettingsPanel::REMOVE,
        ];

        foreach ($actions as $action) {
            self::assertStringContainsString(
                'value="\' . self::' . $this->constantName($action) . ' . \'"',
                $this->panel,
                $action . ' is not rendered as a submit value',
            );
        }

        // The handler accepts exactly those four and nothing else.
        self::assertStringContainsString(
            '\\in_array($action, [self::ACTIVATE, self::ACTIVATE_ADOPTED, self::REFRESH, self::REMOVE], true)',
            $this->panel,
        );

        foreach (['$this->exchange->activate(', '$this->exchange->refresh(', '$this->removal->remove('] as $call) {
            self::assertStringContainsString($call, $this->panel, $call . ' is never reached');
        }
    }

    public function testEveryOperationIsGuardedBeforeItRuns(): void
    {
        self::assertStringContainsString('$this->assertAllowed($request);', $this->panel);
        self::assertStringContainsString('USER_CAN_ACCESS_MODULE', $this->panel);
        self::assertStringContainsString('isTokenValid(new CsrfToken($this->csrfTokenName, $token))', $this->panel);

        // The guard runs before any action is dispatched.
        $guard = strpos($this->panel, '$this->assertAllowed($request);');
        $dispatch = strpos($this->panel, '$outcome = match ($action)');

        self::assertNotFalse($guard);
        self::assertNotFalse($dispatch);
        self::assertTrue($guard < $dispatch, 'permissions and token are checked first');
    }

    public function testTheScreenRedirectsAfterActingSoNothingIsPostedTwice(): void
    {
        self::assertStringContainsString('Controller::redirect($request->getRequestUri());', $this->panel);
    }

    public function testRemovalNeedsAnExplicitConfirmation(): void
    {
        self::assertStringContainsString('removeIfConfirmed', $this->panel);
        self::assertStringContainsString(SettingsPanel::CONFIRM_FIELD, $this->panel);
    }

    public function testTheStateIsReadAndWrittenThroughTheSameStore(): void
    {
        // The card renders from the evaluator, and the evaluator reads the same
        // store the operations commit to.
        self::assertStringContainsString('$this->evaluator->current()', $this->panel);

        $evaluator = $this->read('src/Site/StatusEvaluator.php');
        self::assertStringContainsString('$this->store->readPair()', $evaluator);

        $exchange = $this->read('src/Operation/KeyExchange.php');
        self::assertStringContainsString('$this->store->commit(', $exchange);

        $removal = $this->read('src/Operation/StateRemoval.php');
        self::assertStringContainsString('$this->store->discard()', $removal);
    }

    public function testTheSectionLoadsAndBindsNothing(): void
    {
        // Nothing to load, nothing to bind, so no click can be silently inert.
        foreach (['<script', 'addEventListener', 'data-action=', 'fetch(', 'XMLHttpRequest'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $this->panel, $forbidden);
        }

        // And it never nests a form inside Contao's own settings form.
        self::assertStringNotContainsString('<form', $this->panel);
    }

    public function testTheOnlyBrowserCodeIsTheRemovalPrompt(): void
    {
        // Contao guards its own destructive actions with an inline confirm(),
        // and that attribute is the one and only piece of browser code here.
        self::assertSame(1, substr_count($this->panel, 'onclick'), 'exactly one inline handler');
        self::assertStringContainsString('confirmScript()', $this->panel);
        self::assertStringContainsString('var c=confirm(', $this->panel);

        // It only fills the posted field; it never performs the removal itself.
        self::assertStringNotContainsString('form.submit()', $this->panel);
    }

    public function testARemovalStillFailsClosedWithoutScripting(): void
    {
        // The prompt is a courtesy. The field it fills is posted and checked on
        // the server, so a browser that never ran it removes nothing.
        self::assertStringContainsString(
            '<input type="hidden" name="\' . self::CONFIRM_FIELD . \'" value=""',
            $this->panel,
        );
        self::assertStringContainsString(
            "'1' !== (string) \$request->request->get(self::CONFIRM_FIELD, '')",
            $this->panel,
        );
    }

    public function testImplicitSubmissionCannotTriggerAnAction(): void
    {
        // Pressing Enter in any settings field must land on a button that does
        // nothing, not on activation.
        $noop = strpos($this->panel, 'vts_schemaorg_noop');
        $firstAction = strpos($this->panel, 'name=\'' . SettingsPanel::ACTION_FIELD);

        self::assertNotFalse($noop);
        self::assertTrue(false === $firstAction || $noop < $firstAction);
    }

    public function testTheBrowserNeverTalksToTheLicenceServer(): void
    {
        self::assertStringNotContainsString('v-t.one', SourceText::withoutComments($this->panel));
        self::assertStringNotContainsString(
            'v-t.one',
            SourceText::withoutComments($this->read('src/Controller/PreviewController.php')),
        );
    }

    // ── the push endpoint ────────────────────────────────────────────────

    public function testTheUpdaterPathIsExactAndRegistered(): void
    {
        self::assertStringContainsString('path: /rest/api/v1/schema-org-license-updater', $this->routes);
        self::assertStringContainsString('_controller: VTinnovations\SchemaOrg\Controller\PackageIntakeController', $this->routes);
        self::assertStringContainsString('_token_check: false', $this->routes);
        self::assertStringNotContainsString('methods:', $this->routes, 'a GET must reach the controller and be answered with 405');

        self::assertSame('/rest/api/v1/schema-org-license-updater', (new ProductProfile())->intakePath());
    }

    public function testTheRouteIsLoadedByTheManagerPlugin(): void
    {
        $plugin = $this->read('src/ContaoManager/Plugin.php');

        self::assertStringContainsString('RoutingPluginInterface', $plugin);
        self::assertStringContainsString('config/routes.yaml', $plugin);
    }

    public function testTheControllerAnswersTheDocumentedStatusCodes(): void
    {
        $controller = $this->read('src/Controller/PackageIntakeController.php');

        self::assertStringContainsString('HTTP_METHOD_NOT_ALLOWED', $controller);
        self::assertStringContainsString("'Allow' => 'POST'", $controller);
        self::assertStringContainsString('HTTP_UNSUPPORTED_MEDIA_TYPE', $controller);
        self::assertStringContainsString('HTTP_REQUEST_ENTITY_TOO_LARGE', $controller);
        self::assertStringContainsString('HTTP_UNAUTHORIZED', $controller);
        self::assertStringContainsString('HTTP_FORBIDDEN', $controller);
    }

    // ── container wiring ─────────────────────────────────────────────────

    public function testTheServicesTheScreenNeedsArePublicAndWired(): void
    {
        self::assertStringContainsString("VTinnovations\\SchemaOrg\\DataContainer\\SettingsPanel:\n        public: true", $this->services);
        self::assertStringContainsString("\$csrfTokenName: '%contao.csrf_token_name%'", $this->services);
        self::assertStringContainsString("\$csrfTokenManager: '@contao.csrf.token_manager'", $this->services);
        self::assertStringContainsString("VTinnovations\\SchemaOrg\\Site\\StatusEvaluator:\n        public: true", $this->services);
        self::assertStringContainsString("VTinnovations\\SchemaOrg\\Controller\\PackageIntakeController:\n        public: true", $this->services);
        self::assertStringContainsString("tags: ['controller.service_arguments']", $this->services);
        self::assertStringContainsString('VTinnovations\SchemaOrg\Site\DomainInventory: ', $this->services);
    }

    public function testTheSessionSignalIsClaimedWhenTheSectionIsOpened(): void
    {
        self::assertStringContainsString('$this->signal->claimSectionEntry($request->getSession(), $status)', $this->panel);
        self::assertStringContainsString('$this->signal->releaseClaim(', $this->panel);

        $signal = $this->read('src/Remote/UsageSignal.php');
        self::assertStringContainsString('$session->set($marker, 1);', $signal);
        self::assertStringContainsString('sessionClaimKey', $signal);
    }

    public function testClickingAControlInARunningContaoIsNotCoveredHere(): void
    {
        self::markTestSkipped(
            'browser-level acceptance needs a Contao runtime and a browser driver; neither is installed and neither may be installed here',
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function read(string $relative): string
    {
        $path = \dirname(__DIR__) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function constantName(string $action): string
    {
        return match ($action) {
            SettingsPanel::ACTIVATE => 'ACTIVATE',
            SettingsPanel::ACTIVATE_ADOPTED => 'ACTIVATE_ADOPTED',
            SettingsPanel::REFRESH => 'REFRESH',
            SettingsPanel::REMOVE => 'REMOVE',
            default => $action,
        };
    }
}
