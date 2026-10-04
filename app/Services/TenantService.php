<?php

namespace App\Services;

use App\Enums\MemberType;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Onboards a new nursery: creates the tenant, attaches the owner, and starts a
 * trial on the free plan.
 */
class TenantService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $owner): Tenant
    {
        return DB::transaction(function () use ($data, $owner) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(4),
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $tenant->members()->attach($owner->id, [
                'member_type' => MemberType::Owner->value,
                'status' => 'active',
            ]);

            $freePlan = SubscriptionPlan::where('slug', 'free')->first();

            if ($freePlan !== null) {
                $this->subscriptions->startTrial($tenant, $freePlan);
            }

            return $tenant;
        });
    }
}
