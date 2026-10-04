<?php

namespace App\Models;

use App\Enums\MomentType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MomentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One Daily Wall update. Who sees it is decided only by its tagged children.
 */
class Moment extends Model
{
    /** @use HasFactory<MomentFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'author_id', 'classroom_id', 'type', 'body', 'payload',
        'children_count', 'media_count', 'requires_ack', 'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MomentType::class,
            'payload' => 'array',
            'requires_ack' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsToMany<Child, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'moment_child')
            ->withPivot(['acknowledged_at', 'acknowledged_by']);
    }

    /**
     * @return HasMany<MomentChild, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(MomentChild::class);
    }

    /**
     * @return HasMany<MomentMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MomentMedia::class)->orderBy('sort');
    }
}
