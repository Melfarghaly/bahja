<?php

namespace App\Http\Controllers\Nursery\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\Finance\AssignFeePlanRequest;
use App\Http\Requests\Nursery\Finance\EndFeePlanRequest;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Services\Tuition\ChildFeeService;
use Illuminate\Http\RedirectResponse;

class ChildFeeController extends Controller
{
    public function __construct(private ChildFeeService $fees) {}

    public function store(AssignFeePlanRequest $request, Child $child): RedirectResponse
    {
        $this->fees->assign($child, $request->validated());

        return back()->with('status', 'تم تسجيل الطفل في الرسوم.');
    }

    public function end(EndFeePlanRequest $request, ChildFeePlan $childFeePlan): RedirectResponse
    {
        $this->fees->end($childFeePlan, $request->validated('ends_on'));

        return back()->with('status', 'تم إنهاء تسجيل الرسوم.');
    }
}
