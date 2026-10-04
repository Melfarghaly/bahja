<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DocumentSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Gap-free per-nursery counters for invoice and receipt numbers.
 */
class DocumentSequence extends Model
{
    /** @use HasFactory<DocumentSequenceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'key',
        'last_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
