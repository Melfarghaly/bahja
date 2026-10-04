<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\ChildFeePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A child's enrollment in a fee plan, optionally with a child-specific discount.
 */
class ChildFeePlan extends Model
{
    /** @use HasFactory<ChildFeePlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'child_id',
        'fee_plan_id',
        'fee_discount_id',
        'starts_on',
        'ends_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class, 'fee_discount_id');
    }

    /**
     * Assignments covering any part of the month starting at $period.
     *
     * @param  Builder<ChildFeePlan>  $query
     */
    public function scopeCovering(Builder $query, CarbonInterface $period): void
    {
        $query->whereDate('starts_on', '<=', $period->copy()->endOfMonth())
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $period->copy()->startOfMonth()));
    }
}
