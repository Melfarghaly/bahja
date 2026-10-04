<?php

namespace App\Http\Resources;

use App\Models\Child;
use App\Models\Moment;
use App\Models\MomentMedia;
use App\Services\Wall\MediaUrls;
use App\Services\Wall\MomentSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Moment
 */
class MomentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $urls = app(MediaUrls::class);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'summary' => app(MomentSummary::class)->for($this->resource),
            'body' => $this->body,
            'payload' => (object) ($this->payload ?? []),
            'author' => $this->whenLoaded('author', fn () => $this->author ? ['id' => $this->author->id, 'name' => $this->author->name] : null),
            'classroom' => $this->whenLoaded('classroom', fn () => $this->classroom ? ['id' => $this->classroom->id, 'name' => $this->classroom->name] : null),
            // Staff see every tagged child; a family only its own.
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn (Child $child) => [
                'id' => $child->id,
                'first_name' => $child->first_name,
                'acknowledged_at' => $child->pivot->acknowledged_at ? Carbon::parse($child->pivot->acknowledged_at)->toIso8601String() : null,
            ])),
            'photos' => $this->whenLoaded('media', fn () => $this->media->reject->isVideo()->values()->map(fn (MomentMedia $media) => [
                'id' => $media->id,
                'width' => $media->width,
                'height' => $media->height,
                ...$urls->for($media),
            ])),
            // Streamable (HTTP Range); `poster_url` may be null when the app sent no poster.
            'videos' => $this->whenLoaded('media', fn () => $this->media->filter->isVideo()->values()->map(function (MomentMedia $media) use ($urls) {
                $links = $urls->for($media);

                return [
                    'id' => $media->id,
                    'width' => $media->width,
                    'height' => $media->height,
                    'duration_ms' => $media->duration_ms,
                    'size' => $media->size,
                    'url' => $links['url'],
                    'poster_url' => $links['thumb_url'],
                    'expires_at' => $links['expires_at'],
                ];
            })),
            'requires_ack' => $this->requires_ack,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
