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

namespace VTinnovations\SchemaOrg\Remote;

/**
 * The only destinations this product contacts.
 *
 * Compiled-in constants rather than configuration: no setting, request parameter,
 * DNS alias, redirect or remote response can point the client elsewhere. Each
 * address is reconstructed at runtime and checked against a recorded digest, so a
 * patched literal is refused.
 */
final class EndpointRegistry
{
    private const MASK_SEED = 'vtinnovations/schema-org#endpoint/';

    private const EXCHANGE_MASKED = 'f38f456073b4941db355536c6e6309dab6e3882059243bfa4fc5c620102d70f1e2';
    private const EXCHANGE_DIGEST = 'e8e9b319c612ac72520fc1662061aeb1ac6660436c1080efe77bd64718db835b';

    private const SIGNAL_MASKED = '80ecd185b13e84100518a53b8e92a5d92c72dbc1fc6bcea41b9edf1c5f54e25284f7c2d8a76add50190a';
    private const SIGNAL_DIGEST = '883ea3ff2316adca40eade8c6a936803a49768dc930066e54274f02fba1af43b';

    /** Activation and operator-initiated refresh. */
    public function exchangeUrl(): string
    {
        return $this->reveal('verify', self::EXCHANGE_MASKED, self::EXCHANGE_DIGEST);
    }

    /** Both invocation signals. */
    public function signalUrl(): string
    {
        return $this->reveal('signal', self::SIGNAL_MASKED, self::SIGNAL_DIGEST);
    }

    /**
     * Guards against a URL that came from anywhere but this class.
     */
    public function isPermitted(string $url): bool
    {
        return hash_equals($this->exchangeUrl(), $url) || hash_equals($this->signalUrl(), $url);
    }

    private function reveal(string $name, string $masked, string $digest): string
    {
        $bytes = hex2bin($masked);

        if (!\is_string($bytes) || $bytes === '') {
            throw new \LogicException('A fixed destination is malformed in this build.');
        }

        $seed = hash('sha256', self::MASK_SEED . $name, true);
        $mask = str_repeat($seed, (int) ceil(\strlen($bytes) / \strlen($seed)));
        $url = $bytes ^ substr($mask, 0, \strlen($bytes));

        if (!hash_equals($digest, hash('sha256', $url))) {
            throw new \LogicException('A fixed destination failed its digest check.');
        }

        return $url;
    }
}
