<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\Feature;
use App\Enums\Limit;
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
        'limits',
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
            'limits' => 'array',
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

    /**
     * The plan's features as enums; unknown legacy keys are ignored.
     *
     * @return array<int, Feature>
     */
    public function featureList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $key) => is_string($key) ? Feature::tryFrom($key) : null,
            $this->features ?? [],
        )));
    }

    /**
     * Every quota of the plan keyed by Limit value (null = unlimited).
     *
     * @return array<string, ?int>
     */
    public function limitMap(): array
    {
        return [
            Limit::Children->value => $this->max_children,
            Limit::Staff->value => $this->max_teachers,
            ...array_map(fn ($value) => $value === null ? null : (int) $value, $this->limits ?? []),
        ];
    }
}
