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

/**
 * Minimal stand-in for Contao's palette helper, loaded only when Contao is not
 * installed. It records what each DCA file asked for so the suite can execute
 * the real DCA files and check their fields, options and legends.
 */

namespace Contao\CoreBundle\DataContainer;

if (!class_exists(PaletteManipulator::class, false)) {
    class PaletteManipulator
    {
        public const POSITION_BEFORE = 'before';
        public const POSITION_AFTER = 'after';
        public const POSITION_PREPEND = 'prepend';
        public const POSITION_APPEND = 'append';

        /** @var array<string, array{legends: list<string>, fields: list<string>}> */
        public static array $applied = [];

        /** @var list<string> */
        private array $legends = [];

        /** @var list<string> */
        private array $fields = [];

        public static function create(): self
        {
            return new self();
        }

        public static function reset(): void
        {
            self::$applied = [];
        }

        public function addLegend(string $name, array|string|null $parent = null, string $position = self::POSITION_AFTER, bool $hide = false): self
        {
            $this->legends[] = $name;

            return $this;
        }

        public function addField(array|string $name, array|string|null $parent = null, string $position = self::POSITION_AFTER, mixed $fallback = null, string $fallbackPosition = self::POSITION_APPEND): self
        {
            foreach ((array) $name as $field) {
                $this->fields[] = $field;
            }

            return $this;
        }

        public function applyToPalette(string $palette, string $table): self
        {
            self::$applied[$table]['legends'] = array_values(array_unique(
                array_merge(self::$applied[$table]['legends'] ?? [], $this->legends),
            ));

            self::$applied[$table]['fields'] = array_values(array_unique(
                array_merge(self::$applied[$table]['fields'] ?? [], $this->fields),
            ));

            return $this;
        }
    }
}
