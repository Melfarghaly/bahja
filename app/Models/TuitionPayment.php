<?php

namespace App\Models;

use App\Enums\TuitionPaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\TuitionPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against a family invoice. Never deleted: a mistake is voided,
 * which posts a reversing ledger entry.
 */
class TuitionPayment extends Model
{
    /** @use HasFactory<TuitionPaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tuition_invoice_id',
        'receipt_number',
        'method',
        'amount_piasters',
        'reference',
        'notes',
        'paid_at',
        'received_by',
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
            'method' => TuitionPaymentMethod::class,
            'amount_piasters' => 'integer',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class, 'tuition_invoice_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function amount(): Money
    {
        return Money::of($this->amount_piasters);
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }
}
