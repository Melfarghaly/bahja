<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CouponRedemptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A nursery's use of a coupon, with the discount it locked in.
 */
class CouponRedemption extends Model
{
    /** @use HasFactory<CouponRedemptionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'coupon_id',
        'redeemed_by',
        'percent_off',
        'discount_ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'discount_ends_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Redemptions whose discount still applies.
     *
     * @param  Builder<CouponRedemption>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>', now()));
    }
}
