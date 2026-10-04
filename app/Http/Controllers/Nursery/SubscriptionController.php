<?php

namespace App\Http\Controllers\Nursery;

use App\Enums\Limit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private EntitlementService $entitlements,
        private TenantContext $tenantContext,
    ) {}

    public function show(): View
    {
        $tenant = $this->tenantContext->get();
        $subscription = $tenant->activeSubscription()->with('plan')->first();

        return view('nursery.subscription.show', [
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price_egp')->get(),
            'childrenCount' => Child::count(),
            'staffCount' => $this->entitlements->usage($tenant, Limit::Staff),
            'entitlements' => $this->entitlements->for($tenant),
            'invoices' => Invoice::latest()->limit(12)->get(),
        ]);
    }

    public function changePlan(ChangePlanRequest $request): RedirectResponse
    {
        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $this->subscriptions->changePlan($this->tenantContext->get(), $plan);

        return back()->with('status', "تم تغيير الخطة إلى «{$plan->name}».");
    }
}
