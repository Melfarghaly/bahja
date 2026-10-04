<?php

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Enums\CouponDuration;
use App\Models\Coupon;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin management of subscription coupons. Every change is audited.
 */
class CouponAdminService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $admin): Coupon
    {
        return DB::transaction(function () use ($data, $admin) {
            if (($data['duration'] ?? null) !== CouponDuration::Repeating->value) {
                $data['duration_months'] = null;
            }

            $data['plan_slugs'] = ($data['plan_slugs'] ?? []) ?: null;

            $coupon = Coupon::create($data);

            $this->audit->record(AuditAction::CouponCreated, $coupon, [
                'code' => $coupon->code,
                'percent_off' => $coupon->percent_off,
                'duration' => $coupon->duration->value,
                'max_redemptions' => $coupon->max_redemptions,
            ], $admin);

            return $coupon;
        });
    }

    public function setActive(Coupon $coupon, bool $active, User $admin): Coupon
    {
        return DB::transaction(function () use ($coupon, $active, $admin) {
            $coupon->update(['is_active' => $active]);

            $this->audit->record(AuditAction::CouponStatusChanged, $coupon, [
                'code' => $coupon->code,
                'active' => $active,
            ], $admin);

            return $coupon;
        });
    }
}
