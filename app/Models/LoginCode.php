<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time sign-in code sent by SMS (stored as an HMAC, never in clear).
 */
class LoginCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
