<?php

namespace App\Services\Wall;

use App\Models\MomentMedia;
use Illuminate\Support\Facades\URL;

/**
 * Short-lived signed links to a photo. Generate them only after checking the
 * viewer may see the moment: the link itself is the permission.
 */
class MediaUrls
{
    public const TTL_MINUTES = 30;

    /**
     * @return array{url: string, thumb_url: string, expires_at: string}
     */
    public function for(MomentMedia $media): array
    {
        $expires = now()->addMinutes(self::TTL_MINUTES);
        $link = fn (string $variant) => URL::temporarySignedRoute('media.show', $expires, [
            'tenant' => $media->tenant_id,
            'media' => $media->id,
            'variant' => $variant,
        ]);

        return ['url' => $link('full'), 'thumb_url' => $link('thumb'), 'expires_at' => $expires->toIso8601String()];
    }
}
