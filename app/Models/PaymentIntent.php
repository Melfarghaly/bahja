<?php

namespace App\Models;

use App\Enums\PaymentGatewayName;
use App\Enums\PaymentIntentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\PaymentIntentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One online checkout attempt for a family invoice.
 */
class PaymentIntent extends Model
{
    /** @use HasFactory<PaymentIntentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tuition_invoice_id',
        'payer_id',
        'gateway',
        'amount_piasters',
        'merchant_reference',
        'gateway_reference',
        'checkout_url',
        'payment_code',
        'status',
        'expires_at',
        'completed_at',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGatewayName::class,
            'status' => PaymentIntentStatus::class,
            'amount_piasters' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * "BHG<tenant>-<ulid>": globally unique, and carries the nursery so a
     * webhook can enter the right tenant context before touching any row.
     */
    public static function newMerchantReference(int $tenantId): string
    {
        return 'BHG'.$tenantId.'-'.Str::ulid();
    }

    public static function tenantIdFromReference(string $reference): ?int
    {
        return preg_match('/^BHG(\d+)-[0-9A-Z]{26}$/', $reference, $m) ? (int) $m[1] : null;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class, 'tuition_invoice_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function amount(): Money
    {
        return Money::of($this->amount_piasters);
    }

    public function isReusable(): bool
    {
        return $this->status === PaymentIntentStatus::Pending
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
