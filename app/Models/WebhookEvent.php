<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Every webhook a payment gateway sent us, valid or not. The (provider,
 * event_id) unique key is the first idempotency layer.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'signature_valid',
        'payload',
        'outcome',
        'error',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
