<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use App\Support\TenantContext;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TenantContext $tenantContext,
    ) {}

    public function show(): SubscriptionResource
    {
        $this->authorize('manage', Subscription::class);

        $subscription = $this->tenantContext->get()
            ->activeSubscription()
            ->with('plan')
            ->firstOrFail();

        return new SubscriptionResource($subscription);
    }

    public function changePlan(ChangePlanRequest $request): SubscriptionResource
    {
        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $subscription = $this->subscriptions->changePlan($this->tenantContext->get(), $plan);

        return new SubscriptionResource($subscription->load('plan'));
    }
}
