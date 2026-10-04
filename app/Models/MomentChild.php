<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MomentChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A child tagged in a moment (= the child's family can see it).
 */
class MomentChild extends Model
{
    /** @use HasFactory<MomentChildFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $table = 'moment_child';

    protected $fillable = ['tenant_id', 'moment_id', 'child_id', 'acknowledged_at', 'acknowledged_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Moment, $this>
     */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }
}
