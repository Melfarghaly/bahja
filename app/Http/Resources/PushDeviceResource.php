<?php

namespace App\Http\Resources;

use App\Models\PushDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PushDevice
 */
class PushDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'locale' => $this->locale,
            'app_version' => $this->app_version,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
