<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation helpers bound to the current nursery. `exists` rules run raw
 * queries that bypass the Eloquent TenantScope, so an ID from another nursery
 * would pass a plain `exists:` rule — always constrain it explicitly.
 */
class TenantRules
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', app(TenantContext::class)->id());
    }
}
