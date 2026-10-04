<?php

namespace App\Http\Controllers\Nursery\Finance;

use App\Enums\DiscountType;
use App\Enums\DiscountValueType;
use App\Enums\FeeFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\Finance\SaveFeeDiscountRequest;
use App\Http\Requests\Nursery\Finance\SaveFeePlanRequest;
use App\Http\Requests\Nursery\Finance\ToggleActiveRequest;
use App\Models\Classroom;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Services\Tuition\FeeSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FeeSetupController extends Controller
{
    public function __construct(private FeeSetupService $setup) {}

    public function index(): View
    {
        return view('nursery.finance.setup', [
            'plans' => FeePlan::with('classroom:id,name')->withCount(['assignments as active_children_count' => fn ($q) => $q->whereNull('ends_on')])->orderByDesc('is_active')->orderBy('name')->get(),
            'discounts' => FeeDiscount::orderByDesc('is_active')->orderBy('name')->get(),
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
            'frequencies' => FeeFrequency::cases(),
            'discountTypes' => DiscountType::cases(),
            'valueTypes' => DiscountValueType::cases(),
        ]);
    }

    public function storePlan(SaveFeePlanRequest $request): RedirectResponse
    {
        $this->setup->savePlan($request->validated());

        return back()->with('status', 'تمت إضافة الرسوم.');
    }

    public function updatePlan(SaveFeePlanRequest $request, FeePlan $feePlan): RedirectResponse
    {
        $this->setup->savePlan($request->validated(), $feePlan);

        return back()->with('status', 'تم تحديث الرسوم. التعديل يسري على الفواتير القادمة فقط.');
    }

    public function togglePlan(ToggleActiveRequest $request, FeePlan $feePlan): RedirectResponse
    {
        $this->setup->setPlanActive($feePlan, $request->boolean('is_active'));

        return back()->with('status', 'تم تحديث حالة الرسوم.');
    }

    public function storeDiscount(SaveFeeDiscountRequest $request): RedirectResponse
    {
        $this->setup->saveDiscount($request->validated());

        return back()->with('status', 'تمت إضافة الخصم.');
    }

    public function updateDiscount(SaveFeeDiscountRequest $request, FeeDiscount $feeDiscount): RedirectResponse
    {
        $this->setup->saveDiscount($request->validated(), $feeDiscount);

        return back()->with('status', 'تم تحديث الخصم.');
    }

    public function toggleDiscount(ToggleActiveRequest $request, FeeDiscount $feeDiscount): RedirectResponse
    {
        $this->setup->setDiscountActive($feeDiscount, $request->boolean('is_active'));

        return back()->with('status', 'تم تحديث حالة الخصم.');
    }
}
