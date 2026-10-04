<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price_egp',
        'billing_cycle',
        'max_children',
        'max_teachers',
        'included_sms',
        'features',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_egp' => 'integer',
            'billing_cycle' => BillingCycle::class,
            'max_children' => 'integer',
            'max_teachers' => 'integer',
            'included_sms' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimitedChildren(): bool
    {
        return $this->max_children === null;
    }
}
