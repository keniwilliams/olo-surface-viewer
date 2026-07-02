<?php

return [
    'observer' => [
        'changed_subject' => env('BLOODSTREAM_OBSERVER_CHANGED_SUBJECT', 'olo.bloodstream.observer.changed.v1'),
        'nats_connection' => env('BLOODSTREAM_OBSERVER_NATS_CONNECTION'),
        'listen_timeout' => (float) env('BLOODSTREAM_OBSERVER_LISTEN_TIMEOUT', 1.0),

        // Observer-owned control subject for the Observer memory-write valve.
        // This is an Observer subject, never a Bloodstream subject. Publishing
        // here asks the Observer to pause/resume writing newly discovered
        // visibility memory; it never commands Bloodstream.
        'control_subject' => env('BLOODSTREAM_OBSERVER_CONTROL_SUBJECT', 'olo.bloodstream.observer.memory.write.set.v1'),

        // Identifier recorded on outgoing control requests (audit metadata only).
        'control_requested_by' => env('BLOODSTREAM_OBSERVER_CONTROL_REQUESTED_BY', 'olo-surface-viewer'),
    ],
];
