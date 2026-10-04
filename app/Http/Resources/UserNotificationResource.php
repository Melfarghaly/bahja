<?php

namespace App\Http\Resources;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNotification
 */
class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $text = $this->render();    // in the request language (Accept-Language)

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'priority' => $this->type->priority()->value,
            'title' => $text['title'],
            'body' => $text['body'],
            'child_id' => $this->child_id,
            'deep_link' => (object) ($this->data ?? []),   // where the app should open
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
