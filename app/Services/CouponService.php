<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CouponDuration;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Redeems subscription coupons and prices a nursery's plan after its discount.
 * A nursery holds at most one active discount at a time.
 */
class CouponService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws ValidationException with a user-facing reason on the `code` field.
     */
    public function redeem(Tenant $tenant, string $code, User $by): CouponRedemption
    {
        return DB::transaction(function () use ($tenant, $code, $by) {
            // Lock the coupon row so concurrent redemptions cannot overshoot max_redemptions.
            $coupon = Coupon::where('code', mb_strtoupper(trim($code)))->lockForUpdate()->first();

            $this->assertRedeemable($tenant, $coupon);

            $redemption = CouponRedemption::create([
                'tenant_id' => $tenant->id,
                'coupon_id' => $coupon->id,
                'redeemed_by' => $by->id,
                'percent_off' => $coupon->percent_off,
                'discount_ends_at' => match ($coupon->duration) {
                    CouponDuration::Once => now()->addMonth(),
                    CouponDuration::Repeating => now()->addMonths($coupon->duration_months ?? 1),
                    CouponDuration::Forever => null,
                },
            ]);

            $coupon->increment('redemptions_count');

            $this->audit->record(AuditAction::CouponRedeemed, $redemption, [
                'code' => $coupon->code,
                'percent_off' => $coupon->percent_off,
            ], $by, $tenant->id);

            return $redemption;
        });
    }

    public function activeDiscount(Tenant $tenant): ?CouponRedemption
    {
        return CouponRedemption::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->with('coupon:id,code,name')
            ->latest()
            ->first();
    }

    /**
     * Price after a percentage discount, rounded to the nearest pound.
     */
    public function discountedPrice(int $price, ?CouponRedemption $discount): int
    {
        if ($discount === null) {
            return $price;
        }

        return (int) round($price * (100 - $discount->percent_off) / 100);
    }

    /**
     * @throws ValidationException
     */
    private function assertRedeemable(Tenant $tenant, ?Coupon $coupon): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['code' => $message]);

        if ($coupon === null || ! $coupon->is_active) {
            $fail('كود الخصم غير صحيح.');
        }

        if ($coupon->isExpired()) {
            $fail('انتهت صلاحية كود الخصم.');
        }

        if ($coupon->isExhausted()) {
            $fail('نفدت مرات استخدام هذا الكود.');
        }

        $plan = $tenant->activeSubscription()->with('plan')->first()?->plan;

        if (! $coupon->appliesTo($plan)) {
            $fail('هذا الكود لا ينطبق على خطتك الحالية. يرجى الاشتراك في خطة مدفوعة أولاً.');
        }

        if ($this->activeDiscount($tenant) !== null) {
            $fail('لديك خصم فعّال بالفعل على اشتراكك.');
        }

        $alreadyUsed = CouponRedemption::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('coupon_id', $coupon->id)
            ->exists();

        if ($alreadyUsed) {
            $fail('استخدمت هذا الكود من قبل.');
        }
    }
}
