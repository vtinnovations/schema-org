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
 * Minimal PSR-3 stand-in, loaded only when psr/log is not installed.
 *
 * Enough for the suite to construct services that take a logger and to capture
 * what they log, which is itself something the tests assert on.
 */

namespace Psr\Log;

if (!interface_exists(LoggerInterface::class, false)) {
    interface LoggerInterface
    {
        public function emergency(string|\Stringable $message, array $context = []): void;

        public function alert(string|\Stringable $message, array $context = []): void;

        public function critical(string|\Stringable $message, array $context = []): void;

        public function error(string|\Stringable $message, array $context = []): void;

        public function warning(string|\Stringable $message, array $context = []): void;

        public function notice(string|\Stringable $message, array $context = []): void;

        public function info(string|\Stringable $message, array $context = []): void;

        public function debug(string|\Stringable $message, array $context = []): void;

        public function log($level, string|\Stringable $message, array $context = []): void;
    }
}
