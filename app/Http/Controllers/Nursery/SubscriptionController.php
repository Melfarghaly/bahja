<?php

namespace App\Http\Controllers\Nursery;

use App\Enums\Limit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Http\Requests\Nursery\RedeemCouponRequest;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Services\CouponService;
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
        private CouponService $coupons,
        private TenantContext $tenantContext,
    ) {}

    public function show(): View
    {
        $tenant = $this->tenantContext->get();
        $subscription = $tenant->activeSubscription()->with('plan')->first();
        $discount = $this->coupons->activeDiscount($tenant);

        return view('nursery.subscription.show', [
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price_egp')->get(),
            'childrenCount' => Child::count(),
            'staffCount' => $this->entitlements->usage($tenant, Limit::Staff),
            'entitlements' => $this->entitlements->for($tenant),
            'discount' => $discount,
            'effectivePrice' => $subscription?->plan
                ? $this->coupons->discountedPrice($subscription->plan->price_egp, $discount)
                : null,
            'invoices' => Invoice::latest()->limit(12)->get(),
        ]);
    }

    public function changePlan(ChangePlanRequest $request): RedirectResponse
    {
        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $this->subscriptions->changePlan($this->tenantContext->get(), $plan);

        return back()->with('status', "تم تغيير الخطة إلى «{$plan->name}».");
    }

    public function redeemCoupon(RedeemCouponRequest $request): RedirectResponse
    {
        $redemption = $this->coupons->redeem($this->tenantContext->get(), $request->validated('code'), $request->user());

        return back()->with('status', "تم تفعيل خصم {$redemption->percent_off}% على اشتراكك.");
    }
}
