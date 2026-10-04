<?php

use App\Models\Tenant;
use App\Services\DocumentNumberService;

it('issues sequential numbers per nursery, prefix and year', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $numbers = app(DocumentNumberService::class);

    expect($numbers->next($a, 'INV', 2026))->toBe('INV-2026-000001')
        ->and($numbers->next($a, 'INV', 2026))->toBe('INV-2026-000002')
        ->and($numbers->next($b, 'INV', 2026))->toBe('INV-2026-000001')
        ->and($numbers->next($a, 'RCT', 2026))->toBe('RCT-2026-000001')
        ->and($numbers->next($a, 'INV', 2027))->toBe('INV-2027-000001');
});
