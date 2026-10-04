<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PickupPassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time authorization, issued by a guardian, for someone without a Bahga
 * account to collect a child within a time window.
 */
class PickupPass extends Model
{
    /** @use HasFactory<PickupPassFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'child_id',
        'issued_by',
        'name',
        'phone',
        'code_hash',
        'note',
        'valid_from',
        'valid_until',
        'used_at',
        'revoked_at',
    ];

    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function status(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->used_at !== null => 'used',
            $this->valid_until->isPast() => 'expired',
            $this->valid_from->isFuture() => 'scheduled',
            default => 'active',
        };
    }

    /**
     * @param  Builder<PickupPass>  $query
     */
    public function scopeUsableNow(Builder $query): void
    {
        $query->whereNull('used_at')->whereNull('revoked_at')
            ->where('valid_from', '<=', now())->where('valid_until', '>', now());
    }
}
