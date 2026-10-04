<?php

use App\Enums\AuditAction;
use App\Enums\CouponDuration;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\PlatformMetricsService;
use Database\Seeders\FoundersCouponSeeder;

/**
 * @return array{0: Tenant, 1: User}
 */
function payingNursery(int $price = 1900, string $slug = 'pro'): array
{
    [$tenant, $owner] = createNurseryWithOwner();
    $plan = SubscriptionPlan::where('slug', $slug)->first()
        ?? SubscriptionPlan::factory()->create(['slug' => $slug, 'price_egp' => $price, 'billing_cycle' => 'monthly']);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);

    return [$tenant, $owner];
}

function redeem(User $owner, string $code)
{
    return test()->actingAs($owner)->post(route('nursery.subscription.coupon'), ['code' => $code]);
}

it('applies the founders lifetime discount to a paying nursery', function () {
    (new FoundersCouponSeeder)->run();
    [$tenant, $owner] = payingNursery();

    redeem($owner, ' founders50 ')->assertSessionHasNoErrors()->assertSessionHas('status');

    $redemption = CouponRedemption::withoutGlobalScopes()->sole();
    expect($redemption->tenant_id)->toBe($tenant->id)
        ->and($redemption->percent_off)->toBe(35)
        ->and($redemption->discount_ends_at)->toBeNull()
        ->and(Coupon::sole()->redemptions_count)->toBe(1)
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::CouponRedeemed)->count())->toBe(1);

    $this->actingAs($owner)->get(route('nursery.subscription.show'))
        ->assertOk()
        ->assertSee('1,235')     // 1900 − 35%
        ->assertSee('الخمسون المؤسِّسون');
});

it('stops at the maximum number of redemptions', function () {
    Coupon::factory()->create(['code' => 'ONLY1', 'max_redemptions' => 1]);
    [, $first] = payingNursery();
    [, $second] = payingNursery();

    redeem($first, 'ONLY1')->assertSessionHasNoErrors();
    redeem($second, 'ONLY1')->assertSessionHasErrors(['code' => 'نفدت مرات استخدام هذا الكود.']);
});

it('rejects unknown, inactive and expired codes', function () {
    [, $owner] = payingNursery();
    Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
    Coupon::factory()->create(['code' => 'OLD', 'valid_until' => now()->subDay()]);

    redeem($owner, 'NOPE')->assertSessionHasErrors(['code' => 'كود الخصم غير صحيح.']);
    redeem($owner, 'OFF')->assertSessionHasErrors(['code' => 'كود الخصم غير صحيح.']);
    redeem($owner, 'OLD')->assertSessionHasErrors(['code' => 'انتهت صلاحية كود الخصم.']);
});

it('requires a paid plan that the coupon covers', function () {
    [, $freeOwner] = createNurseryWithOwner();
    Coupon::factory()->create(['code' => 'ANY']);
    Coupon::factory()->create(['code' => 'ADVONLY', 'plan_slugs' => ['advanced']]);
    [, $proOwner] = payingNursery();

    redeem($freeOwner, 'ANY')->assertSessionHasErrors('code');
    redeem($proOwner, 'ADVONLY')->assertSessionHasErrors('code');
});

it('allows only one active discount per nursery', function () {
    [, $owner] = payingNursery();
    Coupon::factory()->create(['code' => 'FIRST']);
    Coupon::factory()->create(['code' => 'SECOND']);

    redeem($owner, 'FIRST')->assertSessionHasNoErrors();
    redeem($owner, 'SECOND')->assertSessionHasErrors(['code' => 'لديك خصم فعّال بالفعل على اشتراكك.']);
});

it('sets the discount end for time-limited coupons', function () {
    [, $owner] = payingNursery();
    Coupon::factory()->create(['code' => 'THREE', 'duration' => CouponDuration::Repeating, 'duration_months' => 3]);

    redeem($owner, 'THREE')->assertSessionHasNoErrors();

    expect(CouponRedemption::withoutGlobalScopes()->sole()->discount_ends_at->isSameDay(now()->addMonths(3)))->toBeTrue();
});

it('forbids teachers from redeeming coupons', function () {
    [$tenant] = payingNursery();
    Coupon::factory()->create(['code' => 'ANY']);
    $teacher = attachTeacher($tenant);

    redeem($teacher, 'ANY')->assertForbidden();
});

it('reports MRR after discounts', function () {
    [$tenant] = payingNursery(1000, 'mrr-plan');
    CouponRedemption::factory()->create(['tenant_id' => $tenant->id, 'percent_off' => 30]);

    expect(app(PlatformMetricsService::class)->monthlyRecurringRevenue())->toBe(700);
});

it('lets a super admin create and pause coupons', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('admin.coupons.store'), [
        'code' => 'ramadan-26', 'name' => 'رمضان', 'percent_off' => 20,
        'duration' => 'repeating', 'duration_months' => 2, 'max_redemptions' => 100,
    ])->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::sole();
    expect($coupon->code)->toBe('RAMADAN-26');

    $this->actingAs($admin)->patch(route('admin.coupons.status', $coupon), ['is_active' => 0])->assertRedirect();
    expect($coupon->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->get(route('admin.coupons.index'))->assertOk()->assertSee('RAMADAN-26');
    $this->actingAs($admin)->get(route('admin.coupons.create'))->assertOk();
});

it('validates coupon creation', function () {
    $admin = User::factory()->superAdmin()->create();
    Coupon::factory()->create(['code' => 'TAKEN']);

    $this->actingAs($admin)->post(route('admin.coupons.store'), [
        'code' => 'taken', 'name' => 'x', 'percent_off' => 150, 'duration' => 'repeating',
    ])->assertSessionHasErrors(['code', 'percent_off', 'duration_months']);
});
