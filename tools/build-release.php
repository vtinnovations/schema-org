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
 * Builds the distributable artefact.
 *
 * What it does:
 *   - refuses to build unless the pinned verification material and the fixed
 *     destinations reconstruct and match their recorded digests, and unless the
 *     test suite passes;
 *   - copies only what ships (no tests, no tooling, no build output);
 *   - strips explanatory comments from shipped PHP while keeping each file's
 *     copyright header, attributes and every framework-visible name;
 *   - checks the built files for readable copies of the addresses and the
 *     verification key, which are meant to be reconstructed at runtime;
 *   - writes a SHA-256 manifest of everything shipped.
 *
 * What it does not do: rename private classes or methods. This package is
 * consumed through Composer and PSR-4, and its service ids, DCA callbacks and
 * route defaults are its class names, so renaming them would have to rewrite
 * the consumer's container too. That limitation is stated rather than papered
 * over with something unsafe.
 *
 * Usage: php tools/build-release.php [--out=build/release] [--skip-tests]
 */

use VTinnovations\SchemaOrg\Config\TrustAnchors;
use VTinnovations\SchemaOrg\Remote\EndpointRegistry;

require \dirname(__DIR__) . '/tests/bootstrap.php';

$root = \dirname(__DIR__);
$options = getopt('', ['out::', 'skip-tests']);
$target = $root . '/' . trim((string) ($options['out'] ?? 'build/release'), '/');
$skipTests = \array_key_exists('skip-tests', $options);

/** Ships to the customer. Everything else does not. */
const SHIPPED = ['src', 'config', 'contao'];
const SHIPPED_FILES = ['composer.json'];

$fail = static function (string $reason): never {
    fwrite(STDERR, "build refused: " . $reason . "\n");

    exit(1);
};

// ── 1. the build may not produce an artefact that cannot verify anything ──

try {
    $anchors = new TrustAnchors();
} catch (Throwable $error) {
    $fail('the pinned verification material did not reconstruct (' . $error->getMessage() . ')');
}

if ($anchors->isEmpty()) {
    $fail('the pinned verification material is empty');
}

foreach ($anchors->identifiers() as $identifier) {
    if (null === $anchors->resolve($identifier, 'ed25519', TrustAnchors::USE_ENVELOPE, time())) {
        $fail('the pinned key "' . $identifier . '" is not usable');
    }
}

$endpoints = new EndpointRegistry();

try {
    $exchangeUrl = $endpoints->exchangeUrl();
    $signalUrl = $endpoints->signalUrl();
} catch (Throwable $error) {
    $fail('a fixed destination did not reconstruct (' . $error->getMessage() . ')');
}

if (!str_starts_with($exchangeUrl, 'https://') || !str_starts_with($signalUrl, 'https://')) {
    $fail('a fixed destination is not HTTPS');
}

echo "verification material: " . implode(', ', $anchors->identifiers()) . "\n";

// ── 2. the suite has to pass ──────────────────────────────────────────────

if (!$skipTests) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/run-tests.php');
    exec($command, $output, $status);

    if (0 !== $status) {
        echo implode("\n", $output), "\n";
        $fail('the test suite did not pass');
    }

    echo "tests: passed\n";
}

// ── 3. copy what ships ────────────────────────────────────────────────────

$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $remove($path . '/' . $entry);
            }
        }

        @rmdir($path);

        return;
    }

    @unlink($path);
};

$remove($target);

if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
    $fail('could not create "' . $target . '"');
}

/**
 * Removes explanatory comments while keeping the file header, attributes and
 * every name the framework resolves at runtime.
 */
$stripComments = static function (string $code): string {
    $tokens = token_get_all($code, TOKEN_PARSE);
    $out = '';
    $headerKept = false;

    foreach ($tokens as $token) {
        if (!\is_array($token)) {
            $out .= $token;

            continue;
        }

        [$id, $text] = $token;

        // The first block comment in a file is its header and stays, whether
        // it is written as /* */ or /** */.
        if ((T_COMMENT === $id || T_DOC_COMMENT === $id) && !$headerKept && str_starts_with($text, '/*')) {
            $headerKept = true;
            $out .= $text;

            continue;
        }

        if (T_COMMENT === $id || T_DOC_COMMENT === $id) {
            $out .= str_contains($text, "\n") ? "\n" : ' ';

            continue;
        }

        $out .= $text;
    }

    return $out;
};

$copied = [];

$copy = static function (string $from, string $to) use (&$copy, &$copied, $stripComments): void {
    if (is_dir($from)) {
        if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
            throw new RuntimeException('could not create ' . $to);
        }

        foreach (scandir($from) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $copy($from . '/' . $entry, $to . '/' . $entry);
            }
        }

        return;
    }

    $contents = (string) file_get_contents($from);

    if (str_ends_with($from, '.php')) {
        $contents = $stripComments($contents);

        // A stripped file that no longer parses would be a silent disaster.
        token_get_all($contents, TOKEN_PARSE);
    }

    file_put_contents($to, $contents);
    $copied[] = $to;
};

try {
    foreach (SHIPPED as $directory) {
        $copy($root . '/' . $directory, $target . '/' . $directory);
    }

    foreach (SHIPPED_FILES as $file) {
        $copy($root . '/' . $file, $target . '/' . $file);
    }
} catch (Throwable $error) {
    $fail('copying failed (' . $error->getMessage() . ')');
}

echo 'copied: ' . \count($copied) . " files\n";

// ── 4. the artefact must not contain readable copies of the fixed material ─

$readable = [
    $exchangeUrl,
    $signalUrl,
    base64_encode((string) $anchors->resolve($anchors->identifiers()[0], 'ed25519', TrustAnchors::USE_ENVELOPE, time())),
];

foreach ($copied as $file) {
    $contents = (string) file_get_contents($file);

    foreach ($readable as $literal) {
        if (str_contains($contents, $literal)) {
            $fail('"' . basename($file) . '" contains a readable copy of fixed material');
        }
    }
}

// The route, the service ids and the DCA field names must survive, since the
// framework resolves them by name.
$survivors = [
    $target . '/config/routes.yaml' => '/rest/api/v1/schema-org-license-updater',
    $target . '/contao/dca/tl_settings.php' => 'vts_schemaorg_panel',
    $target . '/config/services.yaml' => 'VTinnovations\SchemaOrg\DataContainer\SettingsPanel',
    $target . '/contao/languages/en/tl_settings.php' => 'Schema.org Licence management',
];

foreach ($survivors as $file => $needle) {
    if (!is_file($file) || !str_contains((string) file_get_contents($file), $needle)) {
        $fail('"' . $needle . '" did not survive the build');
    }
}

// ── 5. manifest ───────────────────────────────────────────────────────────

$manifest = [];

foreach ($copied as $file) {
    $manifest[str_replace($target . '/', '', $file)] = hash_file('sha256', $file);
}

ksort($manifest);

$document = [
    'package' => 'vtinnovations/schema-org',
    'algorithm' => 'sha256',
    'files' => $manifest,
    'summary' => hash('sha256', implode("\n", array_map(
        static fn (string $path, string $digest): string => $path . ' ' . $digest,
        array_keys($manifest),
        array_values($manifest),
    ))),
];

file_put_contents(
    $target . '/release-manifest.json',
    json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
);

echo "manifest: " . $document['summary'] . "\n";
echo "artefact: " . $target . "\n";
echo "note: private symbols are not renamed; PSR-4 autoloading, service ids, DCA callbacks and route defaults are the class names.\n";

exit(0);
