<?php

namespace App\Models;

use App\Enums\ChildStatus;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'children';

    protected $fillable = [
        'tenant_id',
        'classroom_id',
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'photo_path',
        'medical_notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'status' => ChildStatus::class,
            'medical_notes' => 'array',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * All guardians of this child, with the per-pair permissions carried on the pivot.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'child_guardian', 'child_id', 'guardian_id')
            ->withPivot([
                'relationship',
                'role',
                'can_view_wall',
                'can_pickup',
                'is_payer',
                'custody_flag',
                'notify_preferences',
            ])
            ->withTimestamps();
    }

    /**
     * Guardians explicitly authorized to pick up this child and not custody-blocked.
     * This relationship is the backbone of pickup verification.
     */
    public function authorizedPickups(): BelongsToMany
    {
        return $this->guardians()
            ->wherePivot('can_pickup', true)
            ->wherePivot('custody_flag', '!=', 'blocked');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
