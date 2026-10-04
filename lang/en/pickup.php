<?php

return [
    'invalid_token' => 'Invalid or expired QR code. Ask the guardian to refresh it.',
    'invalid_pass' => 'The pass code is wrong, expired or already used.',
    'no_pickup_rights' => 'You are not authorized to pick up any child in this nursery.',
    'pass_sms' => ':nursery: you are authorized to pick up :child. Pickup code: :code (valid until :until). Show it to the teacher.',
    'reasons' => [
        'custody_blocked' => 'Pickup forbidden by a custody order',
        'not_authorized' => 'Not authorized to pick up this child',
    ],
    'override_requires_manager' => 'Manual override is for nursery managers only.',
    'pass_too_long' => 'A pass cannot be valid for more than :hours hours.',
    'not_present' => 'The child was not checked in today.',
];
