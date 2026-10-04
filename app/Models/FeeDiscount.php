<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\DiscountValueType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\FeeDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable discount: a percentage (basis points) or a fixed amount (piasters).
 */
class FeeDiscount extends Model
{
    /** @use HasFactory<FeeDiscountFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'value_type',
        'value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value_type' => DiscountValueType::class,
            'value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The discount on a given amount — never more than the amount itself.
     */
    public function discountOn(Money $amount): Money
    {
        $discount = $this->value_type === DiscountValueType::Percent
            ? $amount->percentage($this->value)
            : Money::of($this->value);

        return $discount->min($amount);
    }

    public function describe(): string
    {
        return $this->value_type === DiscountValueType::Percent
            ? rtrim(rtrim(number_format($this->value / 100, 2), '0'), '.').'%'
            : Money::of($this->value)->format();
    }
}
