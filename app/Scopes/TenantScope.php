<?php

namespace App\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Application-layer tenant isolation. Automatically constrains every query on a
 * tenant-scoped model to the current tenant. This is the first of two isolation
 * layers; the second is PostgreSQL Row-Level Security in production.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            $builder->where($model->getTable().'.tenant_id', $context->id());
        }
    }
}
