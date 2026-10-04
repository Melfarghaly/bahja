<?php

return [
    'errors' => [
        'validation_failed' => 'The given data was invalid.',
        'unauthenticated' => 'You must sign in first.',
        'forbidden' => 'You are not allowed to perform this action.',
        'not_found' => 'The requested item was not found.',
        'route_not_found' => 'The requested endpoint does not exist.',
        'method_not_allowed' => 'This HTTP method is not supported for this endpoint.',
        'plan_upgrade_required' => 'This feature is not included in your current plan. Please upgrade.',
        'too_many_requests' => 'Too many attempts. Please try again shortly.',
        'server_error' => 'Something went wrong. Please try again later.',
        'no_tenant' => 'No nursery is available for this account.',
        'pickup_not_authorized' => 'This person is not authorized to pick up this child.',
        'plan_limit' => 'Your plan limit (:limit) for “:resource” has been reached. Please upgrade.',
    ],
];
