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

namespace VTinnovations\SchemaOrg\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use VTinnovations\SchemaOrg\Remote\UsageSignal;

/**
 * Sends whatever the request queued, after the response has gone out.
 *
 * Nothing a signal does may make a visitor or an editor wait, and nothing it
 * does may change what they see, so it happens here rather than inline.
 */
#[AsEventListener]
final class DeferredSignalListener
{
    public function __construct(private readonly UsageSignal $signal)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $this->signal->flush();
    }
}
