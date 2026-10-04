<?php

namespace App\Services\Wall;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded photo into what we store: decoded and re-encoded as JPEG
 * (which drops EXIF — GPS location, device, time — entirely), turned upright
 * per its EXIF orientation, at most 2048px, plus a 480px thumbnail.
 */
class PhotoProcessor
{
    public const MAX_SIDE = 2048;

    public const THUMB_SIDE = 480;

    /** Refuse decompression bombs before decoding. */
    public const MAX_PIXELS = 40_000_000;

    private const QUALITY = 82;

    /**
     * @return array{disk: string, path: string, thumb_path: string, mime: string, size: int, width: int, height: int}
     *
     * @throws RuntimeException when the file is not a decodable photo.
     */
    public function store(UploadedFile $file, int $tenantId): array
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('not a usable photo');
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            throw new RuntimeException('not a usable photo');
        }

        $image = $this->upright($image, $file, $info[2]);
        $full = $this->fit($image, self::MAX_SIDE);
        $thumb = $this->fit($image, self::THUMB_SIDE);

        $disk = (string) config('filesystems.wall_disk');
        $base = sprintf('wall/%d/%s/%s', $tenantId, now()->format('Y/m'), Str::uuid());
        $fullBytes = $this->jpeg($full);

        Storage::disk($disk)->put("{$base}.jpg", $fullBytes);
        Storage::disk($disk)->put("{$base}-thumb.jpg", $this->jpeg($thumb));

        return [
            'disk' => $disk,
            'path' => "{$base}.jpg",
            'thumb_path' => "{$base}-thumb.jpg",
            'mime' => 'image/jpeg',
            'size' => strlen($fullBytes),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
    }

    private function upright(GdImage $image, UploadedFile $file, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $image = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * Scaled to fit within $side (never enlarged), on white (PNG transparency).
     */
    private function fit(GdImage $image, int $side): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $side / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        return $canvas;
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
