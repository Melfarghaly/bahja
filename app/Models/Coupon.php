<?php

namespace App\Models;

use App\Enums\CouponDuration;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform-wide discount code on the SaaS subscription.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'percent_off',
        'duration',
        'duration_months',
        'max_redemptions',
        'redemptions_count',
        'plan_slugs',
        'valid_until',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'duration' => CouponDuration::class,
            'duration_months' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'plan_slugs' => 'array',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are case-insensitive: always stored and looked up upper-case.
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtoupper(trim($value)));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isExhausted(): bool
    {
        return $this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function appliesTo(?SubscriptionPlan $plan): bool
    {
        if ($plan === null || $plan->price_egp === 0) {
            return false;
        }

        return $this->plan_slugs === null || in_array($plan->slug, $this->plan_slugs, true);
    }
}
