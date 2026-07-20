<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Security;

use VTinnovations\SchemaOrg\Config\Paths;

/**
 * Persists the cached verification result and decides whether the bundle is
 * unlocked. Paid-only product: a single verify call gates everything (the front
 * end JSON-LD output), so there is no tier and no second gate. State is stored
 * in var/schema-org/license.json.
 *
 * A local bypass (env SCHEMA_ORG_LICENSE_BYPASS=1) unlocks the plugin without a
 * key for development/staging — never set it in production.
 */
final class LicenseManager
{
    /** Trust the cache this long after the last successful verify. */
    private const GRACE = 7 * 86400;

    private const BYPASS_ENV = 'SCHEMA_ORG_LICENSE_BYPASS';

    private string $lastMessage = '';

    public function __construct(
        private readonly Paths $paths,
        private readonly LicenseVerifier $verifier,
    ) {
    }

    public function isBypassed(): bool
    {
        $v = getenv(self::BYPASS_ENV);
        if (false === $v || '' === $v) {
            $v = $_ENV[self::BYPASS_ENV] ?? $_SERVER[self::BYPASS_ENV] ?? '';
        }

        return \in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
    }

    public function getLicenseKey(): string
    {
        return trim((string) ($this->load()['license_key'] ?? ''));
    }

    public function getExpiresAt(): ?int
    {
        $v = $this->load()['license_expires_at'] ?? null;

        return null !== $v ? (int) $v : null;
    }

    public function lastMessage(): string
    {
        return $this->lastMessage;
    }

    public function isLicensed(): bool
    {
        if ($this->isBypassed()) {
            return true;
        }

        $c = $this->load();

        $key = trim((string) ($c['license_key'] ?? ''));
        if ('' === $key) {
            return false;
        }

        $expiresAt = $c['license_expires_at'] ?? null;
        if (null !== $expiresAt && (int) $expiresAt < time()) {
            return false;
        }

        $verifiedAt = (int) ($c['license_verified_at'] ?? 0);
        if (0 === $verifiedAt) {
            return false;
        }

        return time() - $verifiedAt <= self::GRACE;
    }

    public function isCacheStale(int $maxAge = 86400): bool
    {
        $verifiedAt = (int) ($this->load()['license_verified_at'] ?? 0);

        return $verifiedAt > 0 && time() - $verifiedAt > $maxAge;
    }

    /**
     * Verify a freshly entered key and persist the result. On failure the key is
     * kept (so the UI shows which key was rejected) but the verify timestamp
     * stays zeroed — a first activation never relies on the grace window.
     */
    public function activate(string $key, string $domain): bool
    {
        $key = trim($key);

        if ('' === $key || \strlen($key) > 190) {
            $this->persist(['license_key' => '', 'license_verified_at' => 0, 'license_expires_at' => null, 'license_domain' => '', 'license_package' => '']);
            $this->lastMessage = 'No license key entered.';

            return false;
        }

        $result = $this->verifier->verify($key, $domain);
        $this->lastMessage = $result['message'];

        if ($result['valid']) {
            $this->persist([
                'license_key' => $key,
                'license_verified_at' => time(),
                'license_expires_at' => $result['expires_at'],
                'license_domain' => $domain,
                'license_package' => (string) ($result['package'] ?? ''),
            ]);

            return true;
        }

        $this->persist(['license_key' => $key, 'license_verified_at' => 0, 'license_expires_at' => null, 'license_domain' => '', 'license_package' => '']);

        return false;
    }

    /**
     * Background re-check. A transient error keeps the cache so the grace window
     * holds; an explicit denial wipes it so the customer is locked out at once.
     */
    public function refresh(string $domain): void
    {
        $c = $this->load();
        $key = trim((string) ($c['license_key'] ?? ''));

        if ('' === $key) {
            return;
        }

        $useDomain = trim((string) ($c['license_domain'] ?? '')) ?: $domain;
        $result = $this->verifier->verify($key, $useDomain);

        if ($result['valid']) {
            $this->persist([
                'license_verified_at' => time(),
                'license_expires_at' => $result['expires_at'],
                'license_package' => (string) ($result['package'] ?? ($c['license_package'] ?? '')),
            ]);
        } elseif (!$result['server_error']) {
            $this->persist(['license_verified_at' => 0, 'license_expires_at' => null, 'license_domain' => '', 'license_package' => '']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $file = $this->paths->licenseFile();

        if (!is_file($file)) {
            return $this->defaults();
        }

        $raw = file_get_contents($file);
        $data = false !== $raw ? json_decode($raw, true) : null;

        return \is_array($data) ? array_merge($this->defaults(), $data) : $this->defaults();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return ['license_key' => '', 'license_verified_at' => 0, 'license_expires_at' => null, 'license_domain' => '', 'license_package' => ''];
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function persist(array $patch): void
    {
        $merged = array_merge($this->load(), $patch);
        $file = $this->paths->licenseFile();
        $tmp = $file . '.tmp';
        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false !== $json && false !== file_put_contents($tmp, $json, LOCK_EX) && @rename($tmp, $file)) {
            @chmod($file, 0640);
        } else {
            @unlink($tmp);
        }
    }
}
