<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponDuration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponStatusRequest;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Services\Admin\CouponAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CouponController extends Controller
{
    public function __construct(private CouponAdminService $coupons) {}

    public function index(): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'durations' => CouponDuration::cases(),
            'plans' => SubscriptionPlan::where('price_egp', '>', 0)->orderBy('price_egp')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = $this->coupons->create($request->validated(), $request->user());

        return redirect()->route('admin.coupons.index')->with('status', "تم إنشاء الكود {$coupon->code}.");
    }

    public function updateStatus(UpdateCouponStatusRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->coupons->setActive($coupon, $request->boolean('is_active'), $request->user());

        return back()->with('status', "تم تحديث حالة الكود {$coupon->code}.");
    }
}
