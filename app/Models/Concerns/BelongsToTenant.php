<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies tenant isolation to a model: a global scope on reads and automatic
 * tenant_id assignment on creates. Any model using this trait must have a
 * `tenant_id` column.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $context = app(TenantContext::class);

            if (empty($model->tenant_id) && $context->has()) {
                $model->tenant_id = $context->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
