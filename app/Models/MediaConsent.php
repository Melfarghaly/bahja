<?php

namespace App\Models;

use App\Enums\ConsentScope;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MediaConsentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A family's photo permission for a child. Revoking keeps the row (history);
 * the active one has no revoked_at.
 */
class MediaConsent extends Model
{
    /** @use HasFactory<MediaConsentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'child_id', 'scope', 'granted_by', 'granted_at', 'revoked_at', 'revoked_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope' => ConsentScope::class, 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * @param  Builder<MediaConsent>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }
}
