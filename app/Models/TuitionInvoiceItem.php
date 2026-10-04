<?php

namespace App\Models;

use App\Enums\InvoiceItemKind;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\TuitionInvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuitionInvoiceItem extends Model
{
    /** @use HasFactory<TuitionInvoiceItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tuition_invoice_id',
        'child_id',
        'fee_plan_id',
        'fee_discount_id',
        'kind',
        'description',
        'amount_piasters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InvoiceItemKind::class,
            'amount_piasters' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class, 'tuition_invoice_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function amount(): Money
    {
        return Money::of($this->amount_piasters);
    }
}
