<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;

it('lets an owner change the nursery plan', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $current = SubscriptionPlan::factory()->create(['slug' => 'basic', 'max_children' => 40]);
    $target = SubscriptionPlan::factory()->create(['slug' => 'pro', 'max_children' => 100]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $current->id, 'status' => 'active']);

    $this->actingAs($owner)->post(route('nursery.subscription.change-plan'), [
        'subscription_plan_id' => $target->id,
    ])->assertRedirect();

    expect($tenant->activeSubscription()->first()->subscription_plan_id)->toBe($target->id);
});
