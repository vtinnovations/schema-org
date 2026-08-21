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

namespace VTinnovations\SchemaOrg\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use VTinnovations\SchemaOrg\Config\ProductProfile;
use VTinnovations\SchemaOrg\Operation\KeyExchange;
use VTinnovations\SchemaOrg\Operation\Outcome;
use VTinnovations\SchemaOrg\Operation\StateRemoval;
use VTinnovations\SchemaOrg\Remote\ExchangeFailure;
use VTinnovations\SchemaOrg\Remote\UsageSignal;
use VTinnovations\SchemaOrg\Site\InstallationStatus;
use VTinnovations\SchemaOrg\Site\StatusEvaluator;
use VTinnovations\SchemaOrg\Storage\RecordStore;

/**
 * The licence section in Contao → Settings, and the handler behind its buttons.
 *
 * This is the only place in the product where licence state can be changed by a
 * person. Everything is a plain server-rendered form inside Contao's own
 * settings form, so there is nothing to load, nothing to bind and no way for a
 * button to look alive while doing nothing. Each button posts back to the
 * settings module, is checked for module access and for Contao's request token,
 * performs its operation and redirects, so the state shown afterwards is read
 * back from storage rather than assumed.
 *
 * The single exception is the inline `confirm()` on the remove button, which is
 * how Contao itself guards a destructive action. It is a courtesy only: the
 * confirmation is a posted field and the server refuses a removal that does not
 * carry it, so a browser without scripting cannot remove anything by accident
 * either.
 *
 * The section renders synchronously from the current stored state: there is no
 * asynchronous status call and therefore nothing that can sit in a loading
 * state forever.
 */
final class SettingsPanel
{
    public const ACTION_FIELD = 'vts_schemaorg_action';
    public const KEY_FIELD = 'vts_schemaorg_key';
    public const CONFIRM_FIELD = 'vts_schemaorg_confirm';

    public const ACTIVATE = 'activate';
    public const ACTIVATE_ADOPTED = 'activate_adopted';
    public const REFRESH = 'refresh';
    public const REMOVE = 'remove';

    /** Table whose language file carries this section's texts. */
    private const LANGUAGE_FILE = 'tl_settings';

    /*
     * The section paints itself with style attributes rather than a stylesheet
     * of its own, because Contao's backend rules are more specific than
     * anything a section can scope to itself and would win. Every colour is a
     * backend variable, so the card follows the light and dark scheme the user
     * picked without this file knowing which one is active. These three
     * declarations are shared by more than one element, hence the constants;
     * the one-offs stay at the element that uses them.
     */

    private const CARD = 'padding:12px 15px;border:1px solid var(--content-border);border-radius:4px;background:var(--content-bg)';

    private const META = 'font-size:12px;line-height:1.7';

    private const ACTIONS = 'margin-top:8px;display:flex;gap:8px;flex-wrap:wrap';

    /** One stamp format for every date on the card, sortable and unambiguous. */
    private const STAMP = 'Y-m-d H:i';

    public function __construct(
        private readonly StatusEvaluator $evaluator,
        private readonly KeyExchange $exchange,
        private readonly StateRemoval $removal,
        private readonly RecordStore $store,
        private readonly UsageSignal $signal,
        private readonly ProductProfile $profile,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly string $csrfTokenName,
    ) {
    }

    /**
     * Runs before the settings screen is built, on every request to it.
     *
     * A request that carries one of our actions is handled here and ends in a
     * redirect, so the browser never re-posts it and Contao's own settings save
     * is not triggered by a licence button.
     */
    public function handle(DataContainer|null $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return;
        }

        // Take over a key left by an earlier release, whichever way we got here.
        $this->store->adoptSupersededState();

        if (!$request->isMethod('POST')) {
            return;
        }

        $action = (string) $request->request->get(self::ACTION_FIELD, '');

        if (!\in_array($action, [self::ACTIVATE, self::ACTIVATE_ADOPTED, self::REFRESH, self::REMOVE], true)) {
            return;
        }

        $this->assertAllowed($request);

        $entered = trim((string) $request->request->get(self::KEY_FIELD, ''));

        $outcome = match ($action) {
            self::ACTIVATE => $this->exchange->activate($entered),
            self::ACTIVATE_ADOPTED => $this->exchange->activate($this->store->sidecar()['key']),
            self::REFRESH => $this->exchange->refresh($entered !== '' ? $entered : null),
            self::REMOVE => $this->removeIfConfirmed($request),
        };

        $this->report($action, $outcome);

        if ($outcome->ok && $request->hasSession()) {
            // A changed key must be signalled again within this session.
            $this->signal->releaseClaim($request->getSession());
        }

        Controller::redirect($request->getRequestUri());
    }

    /**
     * Renders the section. Called by Contao while building the settings form,
     * so the markup lands inside Contao's own form and inherits its request
     * token.
     */
    public function render(DataContainer|null $dc = null, string $xlabel = ''): string
    {
        $status = $this->evaluator->current();
        $request = $this->requestStack->getCurrentRequest();

        // First time this package's section is opened in this backend session.
        if ($request instanceof Request && $request->hasSession()) {
            $this->signal->claimSectionEntry($request->getSession(), $status);
        }

        $adoptedKey = $this->store->sidecar()['key'];
        $hasState = $this->store->hasPair();

        // The 640px ceiling sits on the outer element, not on the padded box, so
        // the card measures the same as the sibling sections rather than that
        // width plus its own padding.
        $html = '<div class="clr widget vts-licence" style="position:relative;max-width:640px">';
        $html .= '<h3>' . $this->esc($GLOBALS['TL_LANG']['tl_settings']['vts_schemaorg_panel'][0] ?? 'Schema.org') . '</h3>';

        // Implicit submission (Enter in any settings field) must land on a
        // button that does nothing, not on an activation.
        $html .= '<button type="submit" name="vts_schemaorg_noop" value="1" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px"></button>';

        $html .= '<div style="' . self::CARD . '">';
        $html .= $this->statusBlock($status);

        if (!$hasState && $adoptedKey !== '') {
            $html .= '<div class="tl_gray" style="' . self::META . ';margin-top:8px">' . $this->esc($this->label('adopted_note')) . '</div>';
            $html .= '<div style="' . self::ACTIONS . '">'
                . '<button type="submit" name="' . self::ACTION_FIELD . '" value="' . self::ACTIVATE_ADOPTED . '" class="tl_submit">'
                . $this->esc($this->label('btn_activate_adopted')) . '</button></div>';
        }

        $html .= '<label for="ctrl_' . self::KEY_FIELD . '" style="display:block;margin:12px 0 4px"><strong>'
            . $this->esc($this->label('key_label')) . '</strong></label>';
        // Deliberately not "tl_text", and deliberately sized by an attribute
        // rather than a rule of our own: Contao's backend stylesheet is more
        // specific than anything this section could scope to itself, which
        // otherwise leaves the field collapsed and its placeholder clipped.
        $html .= '<input type="text" name="' . self::KEY_FIELD . '" id="ctrl_' . self::KEY_FIELD . '"'
            . ' value="" autocomplete="off" spellcheck="false" maxlength="' . ProductProfile::KEY_MAX_LENGTH . '"'
            . ' style="width:100%;padding:6px;box-sizing:border-box"'
            . ' placeholder="' . $this->esc($this->label('key_placeholder')) . '">';

        // All three controls are always offered, so the section reads the same
        // whatever the state is. Pressing one with nothing stored is answered by
        // the handler, which reports it rather than acting.
        $html .= '<div style="' . self::ACTIONS . '">';
        $html .= '<button type="submit" name="' . self::ACTION_FIELD . '" value="' . self::ACTIVATE . '" class="tl_submit">'
            . $this->esc($this->label('btn_activate')) . '</button>';
        $html .= '<button type="submit" name="' . self::ACTION_FIELD . '" value="' . self::REFRESH . '" class="tl_submit">'
            . $this->esc($this->label('btn_refresh')) . '</button>';

        // The confirmation travels as a posted field. The inline prompt only
        // fills it in; a browser that never runs it posts an empty field and
        // the handler refuses the removal.
        $html .= '<input type="hidden" name="' . self::CONFIRM_FIELD . '" value="">';
        $html .= '<button type="submit" name="' . self::ACTION_FIELD . '" value="' . self::REMOVE . '" class="tl_submit"'
            . ' onclick="' . $this->esc($this->confirmScript()) . '">'
            . $this->esc($this->label('btn_remove')) . '</button>';

        $html .= '</div>';

        return $html . '</div></div>';
    }

    private function statusBlock(InstallationStatus $status): string
    {
        if (InstallationStatus::ACTIVE === $status->state) {
            $tone = 'var(--green)';
            $headline = $this->label('state_active');
        } elseif (InstallationStatus::REFUSED === $status->state) {
            $tone = 'var(--red)';
            $headline = 'host_not_configured' === $status->category || 'no_configured_host' === $status->category
                ? $this->label('err_host')
                : $this->label('state_refused');
        } else {
            // "Nothing activated" reads the same as a refused licence on the
            // sibling sections: in both cases the product does nothing, so both
            // carry the same warning colour.
            $tone = 'var(--red)';
            $headline = $this->label('state_absent');
        }

        $html = $this->headline($tone, $headline);
        $html .= $this->factLine($status);

        if ([] === $status->configuredHosts) {
            $html .= $this->headline('var(--red)', $this->label('no_domain'));
        }

        return $html;
    }

    /**
     * The facts about an active licence as one quiet dot-separated line, the way
     * the other V&T sections show them: masked key, package and the three dates,
     * and nothing else. The domains, the allowance and the document version were
     * dropped — they are record internals nobody acts on from this screen. The
     * key appears only in the masked form the status already carries; the full
     * one is never assembled here.
     */
    private function factLine(InstallationStatus $status): string
    {
        if (InstallationStatus::ACTIVE !== $status->state) {
            return '';
        }

        $facts = [];

        if ('' !== $status->maskedKey) {
            $facts[$this->label('field_key')] = $status->maskedKey;
        }

        $facts[$this->label('field_tier')] = strtoupper((string) $status->tier);

        if (null !== $status->issuedAt) {
            $facts[$this->label('field_from')] = date(self::STAMP, $status->issuedAt);
        }

        $facts[$this->label('field_until')] = $status->perpetual || null === $status->endsAt
            ? $this->label('field_unlimited')
            : date(self::STAMP, $status->endsAt);

        // When the state was last confirmed against the licence server, whether
        // that was an operator action, the cron or a push.
        $checked = $this->store->sidecar()['at'];

        if ($checked > 0) {
            $facts[$this->label('field_checked')] = date(self::STAMP, $checked);
        }

        $parts = [];

        foreach ($facts as $name => $value) {
            $parts[] = $this->esc((string) $name) . ': ' . $this->esc($value);
        }

        return '<div class="tl_gray" style="' . self::META . '">' . implode(' &middot; ', $parts) . '</div>';
    }

    /**
     * Fills the posted confirmation field when the operator agrees, and cancels
     * the submit when they do not. The prompt text is JSON-encoded so a
     * translated sentence cannot break out of the attribute.
     */
    private function confirmScript(): string
    {
        return 'var c=confirm(' . json_encode($this->label('confirm_remove'), JSON_THROW_ON_ERROR) . ');'
            . 'this.form.elements[' . json_encode(self::CONFIRM_FIELD, JSON_THROW_ON_ERROR) . '].value=c?"1":"";'
            . 'return c;';
    }

    private function removeIfConfirmed(Request $request): Outcome
    {
        if ('1' !== (string) $request->request->get(self::CONFIRM_FIELD, '')) {
            return Outcome::failed('not_confirmed');
        }

        return $this->removal->remove();
    }

    /**
     * Module access and Contao's request token are both required before any
     * operation runs — that is, before the stored key is read or the licence
     * server is contacted.
     */
    private function assertAllowed(Request $request): void
    {
        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'settings')) {
            throw new AccessDeniedException('No access to the settings module.');
        }

        $token = (string) $request->request->get('REQUEST_TOKEN', '');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new AccessDeniedException('Invalid request token.');
        }
    }

    /**
     * Turns an internal category into one general sentence. The category itself
     * is recorded in the log, not shown.
     */
    private function report(string $action, Outcome $outcome): void
    {
        if ($outcome->ok) {
            Message::addConfirmation($this->label(match ($action) {
                self::REFRESH => 'ok_refresh',
                self::REMOVE => 'ok_remove',
                default => 'ok_activate',
            }));

            return;
        }

        $message = match (true) {
            'not_confirmed' === $outcome->category => $this->label('err_confirm'),
            // Update and Remove are offered even with nothing stored, so say so
            // plainly instead of reporting a failed operation.
            \in_array($outcome->category, ['no_state', ExchangeFailure::NO_KEY], true) => $this->label('err_no_state'),
            self::REMOVE === $action => $this->label('err_remove'),
            \in_array($outcome->category, ['transport_error', 'remote_server_error'], true) => $this->label('err_unavailable'),
            \in_array($outcome->category, ['host_not_configured', 'no_configured_host', 'host_mismatch'], true) => $this->label('err_host'),
            self::REFRESH === $action => $this->label('err_refresh'),
            default => $this->label('err_generic'),
        };

        Message::addError($message);

        $this->logger->info('schema-org licence action refused', [
            'operation' => $action,
            'result' => $outcome->category,
        ]);
    }

    /**
     * Every visible string comes from the language file, keyed by the package
     * slug so several V&T packages can add their own section without touching
     * each other's texts.
     *
     * A missing translation shows its key rather than an English sentence
     * hidden in the code, which is both how Contao behaves for legends and the
     * only way a gap stays visible.
     */
    private function label(string $key): string
    {
        System::loadLanguageFile(self::LANGUAGE_FILE);

        $translated = $GLOBALS['TL_LANG'][self::LANGUAGE_FILE][$this->profile->slug() . '_licence'][$key] ?? null;

        return \is_string($translated) && $translated !== '' ? $translated : $key;
    }

    private function esc(string $value): string
    {
        return StringUtil::specialchars($value);
    }

    /**
     * The coloured status line. A `div` rather than a `p`, so the backend's own
     * paragraph spacing cannot push the fact line away from it.
     */
    private function headline(string $colour, string $text): string
    {
        return \sprintf(
            '<div style="font-size:15px;font-weight:bold;color:%s;margin-bottom:4px">%s</div>',
            $colour,
            $this->esc($text),
        );
    }
}
