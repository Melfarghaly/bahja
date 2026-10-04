<?php

namespace App\Http\Controllers;

use App\Models\MomentMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a Daily Wall photo behind a signed, expiring URL (img tags send no
 * bearer token: the signature is the permission). Gone with its moment.
 */
class MediaController extends Controller
{
    public function show(int $tenant, MomentMedia $media, string $variant): StreamedResponse
    {
        abort_if($media->moment === null, 404);   // the moment was deleted

        $path = $variant === 'thumb' ? $media->thumb_path : $media->path;
        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        return Storage::disk($media->disk)->response($path, null, [
            'Content-Type' => $media->mime,
            'Cache-Control' => 'private, max-age=1800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
