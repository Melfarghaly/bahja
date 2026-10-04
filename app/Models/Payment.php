<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'amount_egp',
        'status',
        'gateway',
        'gateway_reference',
        'gateway_payload',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_egp' => 'integer',
            'status' => PaymentStatus::class,
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
