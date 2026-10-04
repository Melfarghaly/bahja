<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MomentMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or short video on a moment, on a private disk, only ever reachable
 * through a short-lived signed URL (photos re-encoded: no EXIF / GPS).
 */
class MomentMedia extends Model
{
    /** @use HasFactory<MomentMediaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'moment_media';

    protected $fillable = ['tenant_id', 'moment_id', 'kind', 'disk', 'path', 'thumb_path', 'mime', 'size', 'width', 'height', 'duration_ms', 'sort'];

    public const PHOTO = 'photo';

    public const VIDEO = 'video';

    public function isVideo(): bool
    {
        return $this->kind === self::VIDEO;
    }

    protected $hidden = ['disk', 'path', 'thumb_path'];

    /**
     * @return BelongsTo<Moment, $this>
     */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }
}
