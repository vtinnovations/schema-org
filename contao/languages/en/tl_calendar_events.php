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

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_legend'] = 'Schema.org';

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventStatusOptions'] = [
    'EventScheduled' => 'Scheduled',
    'EventRescheduled' => 'Rescheduled (new date)',
    'EventPostponed' => 'Postponed (open)',
    'EventMovedOnline' => 'Moved online',
    'EventCancelled' => 'Cancelled',
];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventAttendanceOptions'] = [
    'offline' => 'On site',
    'online' => 'Online',
    'mixed' => 'Hybrid (on site + online)',
];

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_disable'] = ['Disable schema', 'No Event JSON-LD for this event.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventStatus'] = ['Event status', 'Empty = scheduled.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventAttendance'] = ['Attendance mode', 'How the event can be attended. Empty = on site.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_location'] = ['Location (name)', 'Overrides the event location.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_customJsonLd'] = ['Custom JSON-LD', 'Additional nodes for the @graph (without @context).'];
