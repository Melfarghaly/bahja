<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of a balanced double-entry posting. Append-only: corrections are
 * made with reversing postings, never by editing or deleting history.
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'transaction_id',
        'account',
        'debit_piasters',
        'credit_piasters',
        'tuition_invoice_id',
        'tuition_payment_id',
        'payer_id',
        'description',
        'posted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'debit_piasters' => 'integer',
            'credit_piasters' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }
}
