<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_legend'] = 'Schema.org';

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventStatusOptions'] = [
    'EventScheduled' => 'Findet statt',
    'EventRescheduled' => 'Verschoben (neuer Termin)',
    'EventPostponed' => 'Verschoben (offen)',
    'EventMovedOnline' => 'Nach online verlegt',
    'EventCancelled' => 'Abgesagt',
];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventAttendanceOptions'] = [
    'offline' => 'Vor Ort',
    'online' => 'Online',
    'mixed' => 'Hybrid (vor Ort + online)',
];

$GLOBALS['TL_LANG']['tl_calendar_events']['schema_disable'] = ['Schema deaktivieren', 'Kein Event-JSON-LD für diesen Termin.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventStatus'] = ['Event-Status', 'Leer = findet statt.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_eventAttendance'] = ['Teilnahmeart', ''];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_location'] = ['Veranstaltungsort (Name)', 'Überschreibt den Ort des Termins.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['schema_customJsonLd'] = ['Eigenes JSON-LD', 'Zusätzliche Knoten für den @graph.'];
