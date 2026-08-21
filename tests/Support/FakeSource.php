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

namespace VTinnovations\SchemaOrg\Tests\Support;

use VTinnovations\SchemaOrg\Remote\ExchangeFailure;
use VTinnovations\SchemaOrg\Remote\PackageSource;
use VTinnovations\SchemaOrg\Site\HostName;

/**
 * Records what was asked for and answers with whatever the test queued.
 */
final class FakeSource implements PackageSource
{
    /** @var list<array{action: string, key: string, host: string, version: int|null}> */
    public array $calls = [];

    private mixed $answer;

    public function __construct(mixed $answer = null)
    {
        $this->answer = $answer;
    }

    public function willReturn(array $delivered): void
    {
        $this->answer = [
            'envelope' => $delivered['envelope'],
            'payload_b64' => $delivered['payload_b64'],
            'request_id' => 'req-1',
        ];
    }

    public function willFail(string $category, bool $transient): void
    {
        $this->answer = new ExchangeFailure($category, $transient);
    }

    public function exchange(string $action, string $key, HostName $host, ?int $currentVersion, int $now): array
    {
        $this->calls[] = [
            'action' => $action,
            'key' => $key,
            'host' => $host->toString(),
            'version' => $currentVersion,
        ];

        if ($this->answer instanceof ExchangeFailure) {
            throw $this->answer;
        }

        if (!\is_array($this->answer)) {
            throw new ExchangeFailure(ExchangeFailure::TRANSPORT, true);
        }

        return $this->answer;
    }
}
