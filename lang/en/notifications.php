<?php

return [
    'child_arrived' => [
        'title' => ':child arrived',
        'body' => ':child arrived at :nursery at :time.',
    ],
    'child_picked_up' => [
        'title' => ':child left',
        'body' => ':child left :nursery with :collector at :time.',
    ],
    'late_pickup' => [
        'title' => ':child has not been picked up',
        'body' => ':nursery: :child has not been picked up yet (pickup time :deadline). Please contact the nursery.',
    ],
    'late_pickup_managers' => [
        'title' => ':child: late pickup',
        'body' => ':nursery: :child is still here more than 45 minutes after the :deadline pickup time.',
    ],
    'moment_posted' => [
        'title' => 'New update about :child',
        'body' => ':teacher: :summary',
    ],
    'incident_reported' => [
        'title' => 'About :child',
        'body' => ':teacher recorded a minor incident for :child. Open the app for details and to acknowledge.',
    ],
];
