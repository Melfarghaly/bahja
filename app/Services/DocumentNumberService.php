<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Issues gap-free, per-nursery, per-year document numbers such as
 * INV-2026-000123 and RCT-2026-000045. The counter row is locked for the
 * duration of the caller's transaction, so concurrent issuers never collide
 * and a rolled-back document does not burn a number.
 */
class DocumentNumberService
{
    public const INVOICE = 'INV';

    public const RECEIPT = 'RCT';

    public function next(Tenant $tenant, string $prefix, ?int $year = null): string
    {
        $key = $prefix.'-'.($year ?? now()->year);

        return DB::transaction(function () use ($tenant, $key) {
            DocumentSequence::withoutGlobalScopes()->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'key' => $key,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_value');

            return sprintf('%s-%06d', $key, $sequence->last_value);
        });
    }
}
