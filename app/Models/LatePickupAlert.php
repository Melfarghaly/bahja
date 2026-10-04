<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LatePickupAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LatePickupAlert extends Model
{
    /** @use HasFactory<LatePickupAlertFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    public const GUARDIANS = 'guardians';

    public const MANAGERS = 'managers';

    protected $fillable = ['tenant_id', 'child_id', 'date', 'stage', 'recipients'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'recipients' => 'integer'];
    }
}
