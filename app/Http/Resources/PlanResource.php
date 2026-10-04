<?php

namespace App\Http\Resources;

use App\Enums\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_egp' => $this->price_egp,
            'billing_cycle' => $this->billing_cycle->value,
            'limits' => [
                'children' => $this->max_children,
                'staff' => $this->max_teachers,
            ],
            'features' => array_map(fn (Feature $f) => ['key' => $f->value, 'label' => $f->label()], $this->featureList()),
        ];
    }
}
