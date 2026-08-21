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

namespace VTinnovations\SchemaOrg\Controller;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SchemaOrg\Schema\SchemaBuilder;
use VTinnovations\SchemaOrg\Site\InstallationStatus;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;

/**
 * BE_MOD callback "Schema.org": a read-only preview of the JSON-LD that the
 * front end response listener injects, with one-click validator links.
 *
 * The preview shows licensed output, so it is gated like the front end. When
 * the installation is not authorised the screen says so once and points at
 * Contao → Settings, where licences are managed — this module has no licence
 * controls of its own and never sees a key.
 *
 * Contao instantiates this via `new`, so services are pulled from the container
 * (declared public).
 */
final class PreviewController
{
    /** Language file holding this module's texts. */
    private const LANGUAGE_FILE = 'vtinnovations_schema';

    /** @var array<string, string> */
    private array $lang = [];

    public function generate(): string
    {
        $this->initLang();

        $container = System::getContainer();

        /** @var StatusEvaluator $evaluator */
        $evaluator = $container->get(StatusEvaluator::class);
        $status = $evaluator->current();

        if (!$status->isEntitled()) {
            return $this->renderLocked();
        }

        return $this->renderDashboard($container, $status);
    }

    private function renderLocked(): string
    {
        return '<div id="tl_buttons"></div>' . $this->styles()
            . '<div id="vtschema">'
            . '<h1 class="vts-head">' . $this->t('title') . '</h1>'
            . '<p class="vts-sub">' . $this->t('subtitle') . '</p>'
            . '<div class="vts-alert vts-alert--warn">' . $this->t('locked') . ' '
            . '<a href="' . $this->esc($this->settingsUrl()) . '">' . $this->t('locked_link') . '</a>'
            . '</div></div>';
    }

    private function renderDashboard(object $container, InstallationStatus $status): string
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

        return '<div id="tl_buttons"></div>' . $this->styles()
            . '<div id="vtschema">'
            . '<h1 class="vts-head">' . $this->t('title') . '</h1>'
            . '<p class="vts-sub">' . $this->t('subtitle') . '</p>'
            . $this->statusLine($status)
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

    /**
     * Read-only summary. The key never appears in full, and licence changes
     * happen in Contao → Settings, not here.
     */
    private function statusLine(InstallationStatus $status): string
    {
        return '<div class="vts-alert vts-alert--ok">'
            . $this->t('active') . ' — ' . $this->esc((string) $status->matchedHost)
            . ' · ' . $this->esc($status->maskedKey)
            . '</div>';
    }

    private function settingsUrl(): string
    {
        try {
            return System::getContainer()->get('router')->generate('contao_backend', ['do' => 'settings']);
        } catch (\Throwable) {
            return '/contao?do=settings';
        }
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
        } catch (\Throwable) {
            // The reason stays internal: an exception message can carry paths.
            return $this->alert('err', $this->t('err_nourl'));
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
            . '<p class="vts-muted" style="font-size:12px;margin:0 0 8px"><strong>' . $this->t('url_label') . ':</strong> '
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

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }

    /**
     * Loads this module's texts. Contao auto-loads only default and modules, so
     * anything else is requested by name and resolved for the backend user's
     * language, with Contao's own fallback behind it.
     */
    private function initLang(): void
    {
        System::loadLanguageFile(self::LANGUAGE_FILE);

        $strings = $GLOBALS['TL_LANG'][self::LANGUAGE_FILE] ?? [];

        $this->lang = \is_array($strings) ? $strings : [];
    }

    /**
     * A missing translation shows its key rather than an invented sentence, so
     * the gap is visible instead of being papered over. This is what Contao
     * itself does for legends and labels.
     */
    private function t(string $key): string
    {
        $value = $this->lang[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : $key;
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
