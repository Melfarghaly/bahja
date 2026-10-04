<?php

namespace App\Models;

use App\Enums\FeeFrequency;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\FeePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fee a nursery charges (monthly tuition, registration, bus…).
 */
class FeePlan extends Model
{
    /** @use HasFactory<FeePlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'amount_piasters',
        'frequency',
        'classroom_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_piasters' => 'integer',
            'frequency' => FeeFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    public function amount(): Money
    {
        return Money::of($this->amount_piasters);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChildFeePlan::class);
    }
}
