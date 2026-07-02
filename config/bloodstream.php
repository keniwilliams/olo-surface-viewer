<?php

return [
    'observer' => [
        'changed_subject' => env('BLOODSTREAM_OBSERVER_CHANGED_SUBJECT', 'olo.bloodstream.observer.changed.v1'),
        'nats_connection' => env('BLOODSTREAM_OBSERVER_NATS_CONNECTION'),
        'listen_timeout' => (float) env('BLOODSTREAM_OBSERVER_LISTEN_TIMEOUT', 1.0),
    ],
];
