<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Controller;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SchemaOrg\Schema\SchemaBuilder;
use VTinnovations\SchemaOrg\Security\LicenseManager;

/**
 * BE_MOD callback "Schema.org". A license-gated dashboard styled in the V&T
 * house look (self-contained, theme-agnostic, scoped under #vtschema):
 *
 *   - not licensed  → activation gate only (nothing else renders)
 *   - licensed      → license status card + JSON-LD preview tool with links to
 *                     the Google Rich Results test and the schema.org validator
 *
 * Contao instantiates this via `new` — services are pulled from the container
 * (declared public).
 */
final class PreviewController
{
    /** @var array<string, string> */
    private array $lang = [];

    public function generate(): string
    {
        $this->initLang();

        $container = System::getContainer();
        /** @var LicenseManager $license */
        $license = $container->get(LicenseManager::class);

        $request = $container->get('request_stack')->getCurrentRequest();
        $notice = '';

        if ($request instanceof Request && $request->isMethod('POST')) {
            if ('save_license' === (string) $request->request->get('vts_action')) {
                if ($license->activate((string) $request->request->get('license_key', ''), $request->getHost())) {
                    Controller::redirect($request->getRequestUri()); // PRG into the unlocked dashboard
                }
                $notice = $this->alert('err', $license->lastMessage() ?: $this->t('license_invalid'));
            }
        }

        // Daily background re-check so a revocation/expiry takes effect on its own.
        if ($request instanceof Request && $license->isCacheStale()) {
            $license->refresh($request->getHost());
        }

        if (!$license->isLicensed()) {
            return $this->renderGate($notice);
        }

        return $this->renderDashboard($container, $license, $notice);
    }

    private function renderGate(string $notice): string
    {
        $token = $this->esc($this->csrfToken());
        $key = $this->esc((System::getContainer()->get(LicenseManager::class))->getLicenseKey());

        return '<div id="tl_buttons"></div>' . $this->styles()
            . '<div id="vtschema">'
            . '<h1 class="vts-head">' . $this->t('title') . '</h1>'
            . '<p class="vts-sub">' . $this->t('license_locked') . '</p>'
            . $notice
            . '<div class="vts-grid"><div class="vts-card">'
            . '<div class="vts-card-h">' . $this->t('license_h') . '</div>'
            . '<div class="vts-card-d">' . $this->t('license_d') . '</div>'
            . '<form method="post" data-turbo="false">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $token . '">'
            . '<input type="hidden" name="vts_action" value="save_license">'
            . '<div class="vts-field"><label>' . $this->t('license_key_label') . '</label>'
            . '<input type="text" name="license_key" class="vts-input" value="' . $key . '" autocomplete="off" spellcheck="false"></div>'
            . '<button type="submit" class="vts-btn">' . $this->t('license_btn') . '</button>'
            . '</form>'
            . '<p class="vts-muted" style="margin:14px 0 0;font-size:12px">' . $this->t('license_cta') . ' <a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a></p>'
            . '</div></div></div>';
    }

    private function renderDashboard(object $container, LicenseManager $license, string $notice): string
    {
        /** @var ContaoFramework $framework */
        $framework = $container->get('contao.framework');
        /** @var SchemaBuilder $builder */
        $builder = $container->get(SchemaBuilder::class);

        /** @var Input $input */
        $input = $framework->getAdapter(Input::class);
        $selectedId = (int) $input->get('page');

        /** @var Adapter $pageAdapter */
        $pageAdapter = $framework->getAdapter(PageModel::class);

        $options = $this->pageOptions($pageAdapter, $selectedId);
        $result = $this->renderResult($pageAdapter, $builder, $selectedId);
        $status = $this->licenseStatus($license);

        return '<div id="tl_buttons"></div>' . $this->styles()
            . '<div id="vtschema">'
            . '<h1 class="vts-head">' . $this->t('title') . '</h1>'
            . '<p class="vts-sub">' . $this->t('subtitle') . '</p>'
            . $notice
            . $status
            . '<div class="vts-card">'
            . '<div class="vts-card-h">' . $this->t('preview_h') . '</div>'
            . '<div class="vts-card-d">' . $this->t('preview_d') . '</div>'
            . '<form method="get" data-turbo="false" class="vts-previewform">'
            . '<input type="hidden" name="do" value="schema">'
            . '<div class="vts-field"><label>' . $this->t('preview_page') . '</label>'
            . '<select name="page" class="vts-input" onchange="this.form.submit()">'
            . '<option value="">' . $this->t('preview_choose') . '</option>' . $options
            . '</select></div>'
            . '<noscript><button type="submit" class="vts-btn">' . $this->t('preview_show') . '</button></noscript>'
            . '</form>'
            . $result
            . '</div></div>';
    }

    private function licenseStatus(LicenseManager $license): string
    {
        if ($license->isBypassed()) {
            return $this->alert('warn', $this->t('license_bypass'));
        }

        $expires = $license->getExpiresAt();
        $when = null !== $expires ? date('d.m.Y', $expires) : $this->t('license_lifetime');
        $key = $license->getLicenseKey();
        $masked = $key !== '' ? substr($key, 0, 4) . '••••' . substr($key, -4) : '';

        return $this->alert('ok', sprintf('%s — %s: %s · %s: %s',
            $this->t('license_active'), $this->t('license_key_label'), $this->esc($masked), $this->t('license_until'), $when));
    }

    private function pageOptions(Adapter $pageAdapter, int $selectedId): string
    {
        $pages = $pageAdapter->findAll(['order' => 'sorting']);
        if ($pages === null) {
            return '';
        }

        $html = '';
        foreach ($pages as $page) {
            if (\in_array($page->type, ['root', 'error_401', 'error_403', 'error_404'], true)) {
                continue;
            }
            $sel = (int) $page->id === $selectedId ? ' selected' : '';
            $label = $this->esc(sprintf('%s  [%s]  #%d', (string) $page->title, (string) $page->type, (int) $page->id));
            $html .= "<option value=\"{$page->id}\"$sel>$label</option>";
        }

        return $html;
    }

    private function renderResult(Adapter $pageAdapter, SchemaBuilder $builder, int $selectedId): string
    {
        if ($selectedId <= 0) {
            return '';
        }

        $page = $pageAdapter->findByPk($selectedId);
        if ($page === null) {
            return $this->alert('err', $this->t('err_notfound'));
        }

        try {
            $url = $page->getAbsoluteUrl();
        } catch (\Throwable $e) {
            return $this->alert('err', $this->t('err_nourl') . ' (' . $this->esc($e->getMessage()) . ')');
        }

        $graph = $builder->buildFor($page, Request::create($url));
        if ($graph->isEmpty()) {
            return $this->alert('warn', $this->t('empty'));
        }

        $pretty = json_encode($graph->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $encUrl = rawurlencode($url);
        $rich = 'https://search.google.com/test/rich-results?url=' . $encUrl;
        $validator = 'https://validator.schema.org/#url=' . $encUrl;

        return '<hr class="vts-sep">'
            . '<p class="vts-muted" style="font-size:12px;margin:0 0 8px"><strong>URL:</strong> '
            . '<a href="' . $this->esc($url) . '" target="_blank" rel="noreferrer">' . $this->esc($url) . '</a></p>'
            . '<p style="margin:0 0 12px">'
            . '<a class="vts-btn vts-btn--ghost" href="' . $this->esc($rich) . '" target="_blank" rel="noreferrer">' . $this->t('btn_rich') . ' →</a> '
            . '<a class="vts-btn vts-btn--ghost" href="' . $this->esc($validator) . '" target="_blank" rel="noreferrer">' . $this->t('btn_validator') . ' →</a>'
            . '</p>'
            . '<pre class="vts-code">' . $this->esc((string) $pretty) . '</pre>';
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function alert(string $type, string $text): string
    {
        $cls = match ($type) {
            'err' => 'vts-alert--err',
            'warn' => 'vts-alert--warn',
            default => 'vts-alert--ok',
        };

        return '<div class="vts-alert ' . $cls . '">' . $text . '</div>';
    }

    private function csrfToken(): string
    {
        $container = System::getContainer();
        $name = (string) $container->getParameter('contao.csrf_token_name');

        return $container->get('contao.csrf.token_manager')->getToken($name)->getValue();
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }

    private function initLang(): void
    {
        $locale = 'de';
        try {
            $request = System::getContainer()->get('request_stack')->getCurrentRequest();
            if ($request !== null) {
                $locale = str_starts_with($request->getLocale(), 'en') ? 'en' : 'de';
            }
        } catch (\Throwable) {
        }

        $de = [
            'title' => 'Schema.org', 'subtitle' => 'Strukturierte Daten (JSON-LD) für Suchmaschinen und KI-Antwortmaschinen.',
            'license_locked' => 'Dieses Plugin ist lizenzpflichtig. Bitte Lizenzschlüssel eingeben, um die JSON-LD-Ausgabe zu aktivieren.',
            'license_h' => 'Lizenz aktivieren', 'license_d' => 'Der Schlüssel wird an den V&T-Lizenzserver geprüft und lokal zwischengespeichert.',
            'license_key_label' => 'Lizenzschlüssel', 'license_btn' => 'Lizenz aktivieren',
            'license_invalid' => 'Lizenzschlüssel ungültig oder abgelehnt.',
            'license_cta' => 'Noch keine Lizenz?', 'license_active' => 'Lizenz aktiv',
            'license_until' => 'gültig bis', 'license_lifetime' => 'unbegrenzt', 'license_bypass' => 'Lizenzprüfung lokal umgangen (SCHEMA_ORG_LICENSE_BYPASS). Nicht in Produktion verwenden.',
            'preview_h' => 'JSON-LD Vorschau', 'preview_d' => 'Zeigt die für eine Seite generierten strukturierten Daten und verlinkt zu den Validatoren.',
            'preview_page' => 'Seite', 'preview_choose' => '— bitte wählen —', 'preview_show' => 'Anzeigen',
            'btn_rich' => 'Google Rich Results Test', 'btn_validator' => 'schema.org Validator',
            'err_notfound' => 'Seite nicht gefunden.', 'err_nourl' => 'Für diesen Seitentyp lässt sich keine URL bilden.',
            'empty' => 'Für diese Seite wird kein Schema ausgegeben (deaktiviert oder keine Daten konfiguriert).',
        ];
        $en = [
            'title' => 'Schema.org', 'subtitle' => 'Structured data (JSON-LD) for search engines and AI answer engines.',
            'license_locked' => 'This plugin requires a license. Enter your key to enable the JSON-LD output.',
            'license_h' => 'Activate license', 'license_d' => 'The key is checked against the V&T license server and cached locally.',
            'license_key_label' => 'License key', 'license_btn' => 'Activate license',
            'license_invalid' => 'License key invalid or rejected.',
            'license_cta' => 'No license yet?', 'license_active' => 'License active',
            'license_until' => 'valid until', 'license_lifetime' => 'lifetime', 'license_bypass' => 'License check bypassed locally (SCHEMA_ORG_LICENSE_BYPASS). Do not use in production.',
            'preview_h' => 'JSON-LD preview', 'preview_d' => 'Shows the structured data generated for a page and links to the validators.',
            'preview_page' => 'Page', 'preview_choose' => '— please choose —', 'preview_show' => 'Show',
            'btn_rich' => 'Google Rich Results Test', 'btn_validator' => 'schema.org validator',
            'err_notfound' => 'Page not found.', 'err_nourl' => 'No URL can be built for this page type.',
            'empty' => 'No schema is emitted for this page (disabled or nothing configured).',
        ];

        $this->lang = 'en' === $locale ? $en : $de;
    }

    private function t(string $key): string
    {
        return $this->lang[$key] ?? $key;
    }

    private function styles(): string
    {
        return <<<'CSS'
<style>
#vtschema{--vt-accent:#5b6cf0;--vt-accent-2:#7c8cff;--vt-bg:rgba(127,127,127,.06);--vt-bd:rgba(127,127,127,.22);--vt-bd-strong:rgba(127,127,127,.42);--vt-r:12px;max-width:1120px;padding:4px 18px 24px;color-scheme:light dark}
#vtschema *{box-sizing:border-box}
#vtschema .vts-head{margin:0 0 4px;font-size:23px;font-weight:700;letter-spacing:-.02em}
#vtschema .vts-sub{margin:0 0 18px;opacity:.7;font-size:13px;line-height:1.55}
#vtschema a{color:var(--vt-accent);text-decoration:none}
#vtschema a:hover{text-decoration:underline}
#vtschema .vts-muted{opacity:.65}
#vtschema .vts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px;margin:0 0 20px}
#vtschema .vts-card{background:var(--vt-bg);border:1px solid var(--vt-bd);border-radius:var(--vt-r);padding:18px 18px 20px;display:flex;flex-direction:column;margin:0 0 18px}
#vtschema .vts-card-h{display:flex;align-items:center;gap:8px;margin:0 0 4px;font-size:15px;font-weight:650}
#vtschema .vts-card-h::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--vt-accent);flex:0 0 9px}
#vtschema .vts-card-d{opacity:.68;font-size:12.5px;line-height:1.5;margin:0 0 14px}
#vtschema form{margin:0}
#vtschema .vts-field{margin:0 0 12px}
#vtschema .vts-field>label{display:block;font-size:12px;font-weight:600;opacity:.82;margin:0 0 5px}
#vtschema .vts-input{width:100%;padding:9px 11px;border:1px solid var(--vt-bd-strong);border-radius:8px;background:rgba(127,127,127,.07);color:inherit;font:inherit;font-size:13px;color-scheme:light dark}
#vtschema .vts-input:focus{outline:0;border-color:var(--vt-accent);box-shadow:0 0 0 3px rgba(91,108,240,.18)}
#vtschema select.vts-input{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path fill='%23888' d='M2 4l4 4 4-4z'/></svg>");background-repeat:no-repeat;background-position:right 11px center;padding-right:30px}
#vtschema select.vts-input option{background:Canvas;color:CanvasText}
#vtschema .vts-btn{display:inline-flex;align-items:center;gap:7px;align-self:flex-start;padding:9px 17px;border:0;border-radius:8px;background:var(--vt-accent);color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:filter .15s;text-decoration:none}
#vtschema .vts-btn:hover{filter:brightness(1.09);text-decoration:none}
#vtschema .vts-btn--ghost{background:transparent;border:1px solid var(--vt-bd-strong);color:inherit}
#vtschema .vts-sep{border:0;border-top:1px dashed var(--vt-bd);margin:16px 0}
#vtschema .vts-alert{border-radius:10px;padding:12px 14px;margin:0 0 16px;font-size:13px;line-height:1.5;border:1px solid var(--vt-bd)}
#vtschema .vts-alert--err{background:rgba(214,80,80,.1);border-color:rgba(214,80,80,.42)}
#vtschema .vts-alert--ok{background:rgba(64,160,110,.1);border-color:rgba(64,160,110,.42)}
#vtschema .vts-alert--warn{background:rgba(214,160,60,.12);border-color:rgba(214,160,60,.45)}
#vtschema .vts-code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;line-height:1.55;max-height:60vh;overflow:auto;background:rgba(0,0,0,.16);border:1px solid var(--vt-bd);border-radius:8px;padding:12px;white-space:pre;word-break:normal;margin:0}
</style>
CSS;
    }
}
