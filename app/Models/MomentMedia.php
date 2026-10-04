<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MomentMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo on a moment: re-encoded (no EXIF / GPS) on a private disk, only
 * ever reachable through a short-lived signed URL.
 */
class MomentMedia extends Model
{
    /** @use HasFactory<MomentMediaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'moment_media';

    protected $fillable = ['tenant_id', 'moment_id', 'disk', 'path', 'thumb_path', 'mime', 'size', 'width', 'height', 'sort'];

    protected $hidden = ['disk', 'path', 'thumb_path'];

    /**
     * @return BelongsTo<Moment, $this>
     */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }
}
