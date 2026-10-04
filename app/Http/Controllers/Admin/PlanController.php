<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\Admin\PlanService;
use App\Services\Exceptions\PlanInUseException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlanController extends Controller
{
    public function __construct(private PlanService $plans) {}

    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('price_egp')
            ->get();

        return view('admin.plans.index', ['plans' => $plans]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new SubscriptionPlan()]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->plans->create($request->validated());

        return redirect()->route('admin.plans.index')->with('status', 'تم إنشاء الخطة.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(UpdatePlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->plans->update($plan, $request->validated());

        return redirect()->route('admin.plans.index')->with('status', 'تم تحديث الخطة.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        try {
            $this->plans->delete($plan);
        } catch (PlanInUseException $e) {
            return back()->with('error', 'لا يمكن حذف خطة مرتبطة باشتراكات قائمة.');
        }

        return redirect()->route('admin.plans.index')->with('status', 'تم حذف الخطة.');
    }
}
