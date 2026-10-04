<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'child_id',
        'date',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'picked_up_by',
        'pickup_verified',
        'check_in_method',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'pickup_verified' => 'boolean',
            'check_in_method' => AttendanceMethod::class,
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }
}
