<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
                'price_egp' => $this->plan->price_egp,
                'max_children' => $this->plan->max_children,
            ]),
        ];
    }
}
