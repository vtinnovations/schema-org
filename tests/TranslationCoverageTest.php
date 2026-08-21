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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use PHPUnit\Framework\TestCase;

/**
 * Every label the extension shows has to come from a language file, in every
 * language the package ships.
 *
 * The DCA files are executed here rather than read as text, so this checks the
 * fields, select options and legends Contao would actually build — a field
 * added later without a label fails, and so does a select whose options were
 * never given a reference and would show raw schema.org type names.
 */
final class TranslationCoverageTest extends TestCase
{
    private const LANGUAGES = ['en', 'de'];

    /**
     * Table => [dca file, minimal seed standing in for what Contao core and the
     * optional bundles have already registered].
     */
    private const TABLES = [
        'tl_page' => [
            'palettes' => [
                '__selector__' => ['type'],
                'root' => '{title_legend},title;{global_legend},language;{expert_legend},cssClass',
                'rootfallback' => '{title_legend},title;{global_legend},language;{expert_legend},cssClass',
                'regular' => '{title_legend},title;{expert_legend},cssClass',
            ],
        ],
        'tl_news' => ['palettes' => ['default' => '{title_legend},headline;{expert_legend},cssClass']],
        'tl_calendar_events' => ['palettes' => ['default' => '{title_legend},title;{expert_legend},cssClass']],
        'tl_faq' => ['palettes' => ['default' => '{title_legend},question;{expert_legend},cssClass']],
        'tl_settings' => ['palettes' => ['default' => '{global_legend},adminEmail']],
    ];

    protected function setUp(): void
    {
        $GLOBALS['TL_LANG'] = [];
        $GLOBALS['TL_DCA'] = [];
        PaletteManipulator::reset();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = [];
        $GLOBALS['TL_DCA'] = [];
    }

    public function testEveryFieldHasALabelAndADescriptionInEveryLanguage(): void
    {
        foreach (self::TABLES as $table => $seed) {
            foreach (self::LANGUAGES as $language) {
                $fields = $this->load($table, $language);

                self::assertNotEmpty($fields, $table . ' registered no fields');

                foreach (array_keys($fields) as $field) {
                    $label = $GLOBALS['TL_LANG'][$table][$field] ?? null;

                    self::assertTrue(
                        \is_array($label),
                        sprintf('%s.%s has no label in "%s"', $table, $field, $language),
                    );
                    self::assertTrue(
                        \is_string($label[0] ?? null) && '' !== $label[0],
                        sprintf('%s.%s has an empty label in "%s"', $table, $field, $language),
                    );
                    self::assertTrue(
                        \is_string($label[1] ?? null) && '' !== $label[1],
                        sprintf('%s.%s has no description in "%s"', $table, $field, $language),
                    );
                }
            }
        }
    }

    public function testEverySelectTranslatesItsOptions(): void
    {
        foreach (self::TABLES as $table => $seed) {
            foreach (self::LANGUAGES as $language) {
                $fields = $this->load($table, $language);

                foreach ($fields as $field => $definition) {
                    if (!\is_array($definition['options'] ?? null)) {
                        continue;
                    }

                    self::assertTrue(
                        \is_array($definition['reference'] ?? null),
                        sprintf('%s.%s has options but no reference, so it would show raw values', $table, $field),
                    );

                    foreach ($definition['options'] as $option) {
                        self::assertTrue(
                            \is_string($definition['reference'][$option] ?? null) && '' !== $definition['reference'][$option],
                            sprintf('%s.%s option "%s" is not translated in "%s"', $table, $field, $option, $language),
                        );
                    }
                }
            }
        }
    }

    public function testEveryLegendHasALabelInEveryLanguage(): void
    {
        foreach (self::TABLES as $table => $seed) {
            foreach (self::LANGUAGES as $language) {
                $this->load($table, $language);

                $legends = PaletteManipulator::$applied[$table]['legends'] ?? [];

                self::assertNotEmpty($legends, $table . ' added no legend');

                foreach ($legends as $legend) {
                    $label = $GLOBALS['TL_LANG'][$table][$legend] ?? null;

                    self::assertTrue(
                        \is_string($label) && '' !== $label,
                        sprintf('legend %s.%s has no label in "%s"', $table, $legend, $language),
                    );
                }
            }
        }
    }

    public function testEveryFieldAddedToAPaletteAlsoExists(): void
    {
        foreach (self::TABLES as $table => $seed) {
            $fields = $this->load($table, 'en');

            foreach (PaletteManipulator::$applied[$table]['fields'] ?? [] as $field) {
                self::assertTrue(
                    isset($fields[$field]),
                    sprintf('%s adds "%s" to a palette but never defines it', $table, $field),
                );
            }
        }
    }

    public function testTheLanguagesDoNotDriftApart(): void
    {
        foreach (self::TABLES as $table => $seed) {
            $keys = [];

            foreach (self::LANGUAGES as $language) {
                $this->load($table, $language);
                $keys[$language] = $this->flatten($GLOBALS['TL_LANG'][$table] ?? []);
                sort($keys[$language]);
            }

            self::assertSame(
                $keys[self::LANGUAGES[0]],
                $keys[self::LANGUAGES[1]],
                $table . ' defines different keys per language',
            );
        }
    }

    // ── the screens ──────────────────────────────────────────────────────

    public function testTheLicenceSectionUsesOnlyTranslatedStrings(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__) . '/src/DataContainer/SettingsPanel.php');

        preg_match_all("/\\\$this->label\('([a-z_]+)'\)/", $source, $matches);
        $used = array_unique($matches[1]);

        self::assertNotEmpty($used);

        foreach (self::LANGUAGES as $language) {
            $this->loadLanguage('tl_settings', $language);
            $strings = $GLOBALS['TL_LANG']['tl_settings']['schema-org_licence'] ?? [];

            foreach ($used as $key) {
                self::assertTrue(
                    \is_string($strings[$key] ?? null) && '' !== $strings[$key],
                    sprintf('the licence section uses "%s", which "%s" does not translate', $key, $language),
                );
            }
        }
    }

    public function testThePreviewModuleUsesOnlyTranslatedStrings(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__) . '/src/Controller/PreviewController.php');

        preg_match_all("/\\\$this->t\('([a-z_]+)'\)/", $source, $matches);
        $used = array_unique($matches[1]);

        self::assertNotEmpty($used);

        foreach (self::LANGUAGES as $language) {
            $this->loadLanguage('vtinnovations_schema', $language);
            $strings = $GLOBALS['TL_LANG']['vtinnovations_schema'] ?? [];

            foreach ($used as $key) {
                self::assertTrue(
                    \is_string($strings[$key] ?? null) && '' !== $strings[$key],
                    sprintf('the preview module uses "%s", which "%s" does not translate', $key, $language),
                );
            }
        }

        // And it asks Contao for the file, because Contao auto-loads only
        // default and modules.
        self::assertStringContainsString('System::loadLanguageFile(self::LANGUAGE_FILE)', $source);
    }

    public function testTheBackendModuleItselfIsNamedInEveryLanguage(): void
    {
        foreach (self::LANGUAGES as $language) {
            $this->loadLanguage('modules', $language);

            self::assertTrue(\is_string($GLOBALS['TL_LANG']['MOD']['schema_org'] ?? null), $language);
            self::assertTrue(\is_array($GLOBALS['TL_LANG']['MOD']['schema'] ?? null), $language);
            self::assertNotEmpty($GLOBALS['TL_LANG']['MOD']['schema'][0], $language);
            self::assertNotEmpty($GLOBALS['TL_LANG']['MOD']['schema'][1], $language);
        }
    }

    public function testTheFrontEndOutputNeverInventsALanguage(): void
    {
        // "inLanguage" is a claim about the document. Guessing it is worse than
        // leaving it out, so it may only ever come from the resolved page.
        foreach (glob(\dirname(__DIR__) . '/src/Schema/Provider/*.php') ?: [] as $provider) {
            $code = (string) file_get_contents($provider);

            preg_match_all("/'inLanguage'\\s*=>\\s*([^,;\\n]+)/", $code, $matches);

            foreach ($matches[1] as $expression) {
                self::assertStringContainsString('$ctx->language()', $expression, basename($provider));
            }
        }

        $context = (string) file_get_contents(\dirname(__DIR__) . '/src/Schema/SchemaContext.php');

        self::assertStringNotContainsString("'de'", $context);
        self::assertStringNotContainsString("'en'", $context);
    }

    public function testNothingInTheSourcePicksALanguageByItself(): void
    {
        // Contao resolves the backend user's language and the page language.
        // Nothing here may second-guess that with its own locale switch.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__) . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            self::assertStringNotContainsString('getLocale()', $code, $file->getFilename());
            self::assertStringNotContainsString('setlocale', $code, $file->getFilename());
        }
    }

    public function testNoScreenCarriesItsOwnCopyOfTheTexts(): void
    {
        // A fallback map in PHP is a translation nobody can change, and it
        // silently wins over the language file.
        foreach (['src/DataContainer/SettingsPanel.php', 'src/Controller/PreviewController.php'] as $file) {
            $source = (string) file_get_contents(\dirname(__DIR__) . '/' . $file);

            self::assertStringNotContainsString('FALLBACK_LABELS', $source, $file);
            self::assertStringNotContainsString("\$de = [", $source, $file);
            self::assertStringNotContainsString("\$en = [", $source, $file);
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Loads a language file and then the DCA file, so that the DCA's `reference`
     * pointers bind to populated arrays exactly as they do in Contao.
     *
     * @return array<string, array<string, mixed>>
     */
    private function load(string $table, string $language): array
    {
        $GLOBALS['TL_LANG'] = [];
        $GLOBALS['TL_DCA'] = [];
        PaletteManipulator::reset();

        $this->loadLanguage($table, $language);

        $GLOBALS['TL_DCA'][$table] = ['fields' => []] + self::TABLES[$table];

        require \dirname(__DIR__) . '/contao/dca/' . $table . '.php';

        /** @var array<string, array<string, mixed>> $fields */
        $fields = $GLOBALS['TL_DCA'][$table]['fields'];

        // Only the fields this package adds are ours to translate.
        return array_filter(
            $fields,
            static fn (string $name): bool => str_starts_with($name, 'schema_') || str_starts_with($name, 'vts_'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function loadLanguage(string $name, string $language): void
    {
        $file = \dirname(__DIR__) . '/contao/languages/' . $language . '/' . $name . '.php';

        self::assertFileExists($file, 'missing language file');

        require $file;
    }

    /**
     * @param array<string, mixed> $strings
     *
     * @return list<string>
     */
    private function flatten(array $strings, string $prefix = ''): array
    {
        $keys = [];

        foreach ($strings as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

            if (\is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
