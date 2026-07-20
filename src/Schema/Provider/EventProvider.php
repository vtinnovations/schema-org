<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/schema-org
 * @author    V&T Innovations
 * @license   LGPL-3.0-or-later
 * @copyright V&T Innovations 2026
 */

namespace VTinnovations\SchemaOrg\Schema\Provider;

use Doctrine\DBAL\Connection;
use VTinnovations\SchemaOrg\Config\SchemaConfig;
use VTinnovations\SchemaOrg\Schema\NodeProviderInterface;
use VTinnovations\SchemaOrg\Schema\SchemaContext;
use VTinnovations\SchemaOrg\Schema\SchemaGraph;

/**
 * Event node for a Contao calendar event detail page (start/end, status,
 * attendance mode, location, organizer).
 */
final class EventProvider implements NodeProviderInterface
{
    private const ATTENDANCE = [
        'offline' => 'https://schema.org/OfflineEventAttendanceMode',
        'online' => 'https://schema.org/OnlineEventAttendanceMode',
        'mixed' => 'https://schema.org/MixedEventAttendanceMode',
    ];

    public function __construct(
        private readonly SchemaConfig $config,
        private readonly Connection $connection,
    ) {
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function contribute(SchemaContext $ctx, SchemaGraph $graph): void
    {
        if ($ctx->autoItem === null) {
            return;
        }

        try {
            $event = $this->connection->fetchAssociative(
                'SELECT * FROM tl_calendar_events WHERE alias = ? AND published = ? LIMIT 1',
                [$ctx->autoItem, '1'],
            );
        } catch (\Throwable) {
            return; // calendar bundle not installed
        }

        if ($event === false || !empty($event['schema_disable'])) {
            return;
        }

        $addTime = !empty($event['addTime']);
        $start = $addTime ? (int) $event['startTime'] : (int) $event['startDate'];
        if ($start <= 0) {
            return;
        }

        $node = [
            '@type' => 'Event',
            '@id' => $ctx->primaryEntityId(),
            'name' => (string) $event['title'],
            'url' => $ctx->pageUrl,
            'startDate' => date($addTime ? 'c' : 'Y-m-d', $start),
            'mainEntityOfPage' => $graph->ref($ctx->webPageId()),
        ];

        $end = $addTime ? (int) ($event['endTime'] ?? 0) : (int) ($event['endDate'] ?? 0);
        if ($end > $start) {
            $node['endDate'] = date($addTime ? 'c' : 'Y-m-d', $end);
        }

        $teaser = trim(strip_tags((string) ($event['teaser'] ?? '')));
        if ($teaser !== '') {
            $node['description'] = $teaser;
        }

        $status = trim((string) ($event['schema_eventStatus'] ?? ''));
        $node['eventStatus'] = 'https://schema.org/' . ($status !== '' ? $status : 'EventScheduled');

        $attendance = trim((string) ($event['schema_eventAttendance'] ?? ''));
        if ($attendance !== '' && isset(self::ATTENDANCE[$attendance])) {
            $node['eventAttendanceMode'] = self::ATTENDANCE[$attendance];
        }

        // Location: explicit override, else the event's own location text.
        $locationName = trim((string) ($event['schema_location'] ?? ''));
        if ($locationName === '') {
            $locationName = trim((string) ($event['location'] ?? ''));
        }
        if ($locationName !== '') {
            $node['location'] = ['@type' => 'Place', 'name' => $locationName];
        }

        if (!empty($event['addImage']) && !empty($event['singleSRC'])) {
            $image = $this->config->fileUrl($event['singleSRC'], $ctx->baseUrl);
            if ($image !== '') {
                $node['image'] = ['@type' => 'ImageObject', 'url' => $image, 'contentUrl' => $image];
            }
        }

        if ($this->config->showsOrganization($ctx->rootPage)) {
            $node['organizer'] = $graph->ref($ctx->organizationId());
        }

        $graph->add($node);

        $graph->add([
            '@id' => $ctx->webPageId(),
            'mainEntity' => $graph->ref($ctx->primaryEntityId()),
        ]);
    }
}
