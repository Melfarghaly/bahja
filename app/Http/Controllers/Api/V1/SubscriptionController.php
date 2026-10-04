<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private EntitlementService $entitlements,
        private TenantContext $tenantContext,
    ) {}

    public function show(): SubscriptionResource
    {
        $this->authorize('manage', Subscription::class);

        $tenant = $this->tenantContext->get();
        $subscription = $tenant->activeSubscription()->with('plan')->firstOrFail();

        return (new SubscriptionResource($subscription))->additional([
            'entitlements' => $this->entitlementsOf($tenant),
        ]);
    }

    /**
     * Plans the nursery can switch to.
     */
    public function plans(): AnonymousResourceCollection
    {
        $this->authorize('manage', Subscription::class);

        return PlanResource::collection(SubscriptionPlan::where('is_active', true)->orderBy('price_egp')->get());
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementsOf(Tenant $tenant): array
    {
        $entitlements = $this->entitlements->for($tenant);

        return [
            'plan_name' => $entitlements->planName,
            'features' => array_map(fn (Feature $f) => $f->value, $entitlements->features()),
            'limits' => collect([Limit::Children, Limit::Staff])->mapWithKeys(fn (Limit $limit) => [$limit->value => [
                'limit' => $entitlements->limit($limit),
                'used' => $this->entitlements->usage($tenant, $limit),
            ]])->all(),
        ];
    }

    public function changePlan(ChangePlanRequest $request): SubscriptionResource
    {
        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $tenant = $this->tenantContext->get();
        $subscription = $this->subscriptions->changePlan($tenant, $plan);

        return (new SubscriptionResource($subscription->load('plan')))->additional([
            'entitlements' => $this->entitlementsOf($tenant),
        ]);
    }
}
