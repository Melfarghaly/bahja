<?php

namespace App\Http\Controllers;

use App\Models\MomentMedia;
use App\Services\Wall\MediaUrls;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Daily Wall photo or video behind a signed, expiring URL (img and
 * video tags send no bearer token: the signature is the permission). Gone
 * with its moment.
 */
class MediaController extends Controller
{
    public function show(int $tenant, MomentMedia $media, string $variant): Response
    {
        abort_if($media->moment === null, 404);   // the moment was deleted

        $path = $variant === 'thumb' ? $media->thumb_path : $media->path;
        $disk = Storage::disk($media->disk);
        abort_unless($path !== null && $disk->exists($path), 404);

        $headers = [
            'Content-Type' => $variant === 'thumb' ? 'image/jpeg' : $media->mime,
            'Cache-Control' => 'private, max-age=1800',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Local disk: a file response answers Range requests (video seeking,
        // iOS players require it) and is sent without loading it in PHP.
        if ($disk instanceof FilesystemAdapter && $disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return response()->file($disk->path($path), $headers);
        }

        // Cloud disk (S3 / R2): hand over to a short-lived URL of the bucket,
        // which serves ranges and offloads the bandwidth.
        return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(MediaUrls::TTL_MINUTES)));
    }
}
