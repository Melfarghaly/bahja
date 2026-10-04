<?php

namespace App\Models;

use App\Enums\MemberType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A global identity. The same user may be an owner in one nursery, a teacher in
 * another, and a guardian in a third. Tenant membership and per-tenant roles
 * live in pivot tables, never on this record.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar_path',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Tenants this user belongs to in any capacity, with the membership role.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(['member_type', 'status'])
            ->withTimestamps();
    }

    /**
     * Children this user is a guardian of (the unified siblings view), with
     * the per-pair permissions carried on the pivot.
     */
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_guardian', 'guardian_id', 'child_id')
            ->withPivot([
                'relationship',
                'role',
                'can_view_wall',
                'can_pickup',
                'is_payer',
                'billing_share_bp',
                'custody_flag',
                'notify_preferences',
            ])
            ->withTimestamps();
    }

    /**
     * Nurseries this user works at as a teacher.
     */
    public function nurseriesAsTeacher(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'teacher_nursery', 'teacher_id', 'tenant_id')
            ->withPivot(['role', 'status', 'employment_type', 'classroom_id'])
            ->withTimestamps();
    }

    /**
     * Whether the user is a platform super admin (operates across all tenants).
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Whether the user holds an owner/admin membership in the given tenant.
     */
    public function manages(Tenant $tenant): bool
    {
        return $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivotIn('member_type', [MemberType::Owner->value, MemberType::Admin->value])
            ->exists();
    }

    /**
     * Whether the user is a teacher in the given tenant.
     */
    public function teachesIn(Tenant $tenant): bool
    {
        return $this->nurseriesAsTeacher()
            ->where('tenants.id', $tenant->id)
            ->exists();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
