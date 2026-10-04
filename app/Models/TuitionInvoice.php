<?php

namespace App\Models;

use App\Enums\TuitionInvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\TuitionInvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A family invoice: one per payer per billing period, covering all of the
 * payer's children. Amounts are piasters; the ledger is the source of truth,
 * `paid_piasters` is a cache kept in sync by the payment service.
 */
class TuitionInvoice extends Model
{
    /** @use HasFactory<TuitionInvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'number',
        'payer_id',
        'period_start',
        'issued_on',
        'due_on',
        'subtotal_piasters',
        'discount_piasters',
        'total_piasters',
        'paid_piasters',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal_piasters' => 'integer',
            'discount_piasters' => 'integer',
            'total_piasters' => 'integer',
            'paid_piasters' => 'integer',
            'status' => TuitionInvoiceStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TuitionInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TuitionPayment::class);
    }

    public function total(): Money
    {
        return Money::of($this->total_piasters);
    }

    public function paid(): Money
    {
        return Money::of($this->paid_piasters);
    }

    public function balance(): Money
    {
        return $this->status === TuitionInvoiceStatus::Void
            ? Money::zero()
            : Money::of($this->total_piasters - $this->paid_piasters);
    }

    public function isOverdue(): bool
    {
        return $this->status->isCollectible() && $this->due_on->isBefore(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_on->diffInDays(today()) : 0;
    }

    /**
     * @param  Builder<TuitionInvoice>  $query
     */
    public function scopeCollectible(Builder $query): void
    {
        $query->whereIn('status', [TuitionInvoiceStatus::Open->value, TuitionInvoiceStatus::PartiallyPaid->value]);
    }
}
