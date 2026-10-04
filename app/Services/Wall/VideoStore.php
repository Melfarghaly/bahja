<?php

namespace App\Services\Wall;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a short video as uploaded (the app compresses it before upload) on
 * the private wall disk, with its poster re-encoded like any photo.
 */
class VideoStore
{
    public function __construct(private PhotoProcessor $photos) {}

    /**
     * @return array{kind: string, disk: string, path: string, thumb_path: ?string, mime: string, size: int, width: ?int, height: ?int, duration_ms: ?int}
     */
    public function store(UploadedFile $video, ?UploadedFile $poster, ?int $durationMs, int $tenantId): array
    {
        $disk = (string) config('filesystems.wall_disk');
        $directory = sprintf('wall/%d/%s', $tenantId, now()->format('Y/m'));
        $extension = $video->getMimeType() === 'video/quicktime' ? 'mov' : 'mp4';

        $path = Storage::disk($disk)->putFileAs($directory, $video, Str::uuid().'.'.$extension);
        $still = $poster ? $this->photos->poster($poster, $tenantId) : null;

        return [
            'kind' => 'video',
            'disk' => $disk,
            'path' => (string) $path,
            'thumb_path' => $still['path'] ?? null,
            'mime' => (string) $video->getMimeType(),
            'size' => (int) $video->getSize(),
            'width' => $still['width'] ?? null,
            'height' => $still['height'] ?? null,
            'duration_ms' => $durationMs,
        ];
    }
}
