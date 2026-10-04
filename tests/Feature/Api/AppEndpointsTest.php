<?php

use App\Models\Attendance;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EntitlementService;
use App\Services\GuardianService;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    $this->teacher = attachTeacher($this->tenant);
    $this->classroom = Classroom::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'عباد الشمس']);
    $this->yousef = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'يوسف', 'classroom_id' => $this->classroom->id]);
    $this->layla = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'ليلى']);

    $this->mother = User::factory()->create(['name' => 'منى']);
    $this->father = User::factory()->create(['name' => 'أحمد']);
    $guardians = app(GuardianService::class);
    $guardians->attach($this->yousef, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true, 'is_payer' => true]);
    $guardians->attach($this->yousef, $this->father, ['relationship' => 'father', 'role' => 'viewer', 'custody_flag' => 'blocked']);
});

it('describes the user and their roles and capabilities per nursery', function () {
    $this->actingAs($this->owner)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.nurseries.0.name', 'حضانة البراعم')
        ->assertJsonPath('data.nurseries.0.roles', ['owner'])
        ->assertJsonPath('data.nurseries.0.capabilities.manage_children', true);

    $this->actingAs($this->teacher)->getJson('/api/v1/me')
        ->assertJsonPath('data.nurseries.0.capabilities.take_attendance', true)
        ->assertJsonPath('data.nurseries.0.capabilities.manage_children', false);

    $this->actingAs($this->mother)->getJson('/api/v1/me')
        ->assertJsonPath('data.nurseries.0.roles', ['guardian'])
        ->assertJsonPath('data.nurseries.0.capabilities.guardian', true)
        ->assertJsonPath('data.nurseries.0.capabilities.view_children', false);
});

it('gives a teacher the daily attendance sheet with a summary', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);

    $this->actingAs($this->teacher)->getJson('/api/v1/attendance')
        ->assertOk()
        ->assertJsonPath('summary', ['total' => 2, 'present' => 1, 'picked_up' => 0, 'absent' => 1, 'late_pickup' => 0])
        ->assertJsonPath('date', today()->toDateString())
        ->assertJsonFragment(['status' => 'present']);

    $this->actingAs($this->teacher)->getJson('/api/v1/attendance?classroom_id='.$this->classroom->id)
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->teacher)->getJson('/api/v1/attendance?date='.today()->addDay()->toDateString())
        ->assertUnprocessable()->assertJsonValidationErrors('date');
});

it('keeps guardians out of staff endpoints', function () {
    $this->actingAs($this->mother)->getJson('/api/v1/attendance')->assertForbidden();
    $this->actingAs($this->mother)->getJson('/api/v1/children')->assertForbidden();
    $this->actingAs($this->mother)->getJson("/api/v1/children/{$this->yousef->id}")->assertForbidden();
    $this->actingAs($this->mother)->getJson('/api/v1/classrooms')->assertForbidden();
});

it('filters the children list and exposes custody flags to staff', function () {
    $this->actingAs($this->teacher)->getJson('/api/v1/children?q=يوس')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'يوسف');

    $this->actingAs($this->teacher)->getJson("/api/v1/children/{$this->yousef->id}")
        ->assertJsonFragment(['custody_flag' => 'blocked']);
});

it('lists classrooms with their active children count', function () {
    $this->actingAs($this->teacher)->getJson('/api/v1/classrooms')
        ->assertOk()->assertJsonPath('data.0.name', 'عباد الشمس')->assertJsonPath('data.0.children_count', 1);
});

it('shows a guardian only their own link, never co-guardians', function () {
    $response = $this->actingAs($this->mother)->getJson('/api/v1/me/wards')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.my_link.can_pickup', true)
        ->assertJsonPath('data.0.my_link.notifications.sms', true);

    expect($response->getContent())->not->toContain('أحمد')->not->toContain($this->father->phone);
});

it('hides the child completely from a custody-blocked guardian', function () {
    $this->actingAs($this->father)->getJson('/api/v1/me/wards')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->father)->getJson("/api/v1/me/wards/{$this->yousef->id}")->assertNotFound();
    $this->actingAs($this->father)->getJson("/api/v1/me/wards/{$this->yousef->id}/attendance")->assertNotFound();
});

it('gives a guardian their child\'s attendance history', function () {
    Attendance::factory()->create(['tenant_id' => $this->tenant->id, 'child_id' => $this->yousef->id, 'date' => '2026-09-01']);
    Attendance::factory()->create(['tenant_id' => $this->tenant->id, 'child_id' => $this->yousef->id, 'date' => '2026-09-15']);

    $this->actingAs($this->mother)->getJson("/api/v1/me/wards/{$this->yousef->id}/attendance?from=2026-09-10")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.date', '2026-09-15');

    $this->actingAs($this->mother)->getJson("/api/v1/me/wards/{$this->layla->id}")->assertNotFound();   // not her child
});

it('lets a guardian turn SMS reminders off for a child', function () {
    $this->actingAs($this->mother)->patchJson("/api/v1/me/wards/{$this->yousef->id}/notifications", ['sms' => false])
        ->assertOk()->assertJsonPath('data.my_link.notifications.sms', false);

    $this->actingAs($this->mother)->patchJson("/api/v1/me/wards/{$this->yousef->id}/notifications", [])
        ->assertUnprocessable()->assertJsonValidationErrors('sms');
});

it('tells the app which online payment methods are offered', function () {
    enableBahgaPay($this->tenant);
    configureGateways();

    $this->actingAs($this->mother)->getJson('/api/v1/me/payment-methods')
        ->assertOk()->assertJsonPath('online_payments_enabled', false)->assertJsonPath('data', []);

    TenantEntitlementOverride::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'auto_collection', 'value' => true]);
    app(EntitlementService::class)->forget($this->tenant);   // memoized per request; tests share one app

    $this->actingAs($this->mother)->getJson('/api/v1/me/payment-methods')
        ->assertJsonPath('online_payments_enabled', true)
        ->assertJsonPath('data.0.gateway', 'paymob')
        ->assertJsonPath('data.1.kind', 'payment_code');
});

it('shows the owner the subscription with usage, and the plans to switch to', function () {
    (new SubscriptionPlanSeeder)->run();
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'basic')->value('id'), 'status' => 'active']);

    $this->actingAs($this->owner)->getJson('/api/v1/subscription')
        ->assertOk()
        ->assertJsonPath('entitlements.limits.children', ['limit' => 50, 'used' => 2]);

    $this->actingAs($this->owner)->getJson('/api/v1/subscription/plans')
        ->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.0.slug', 'free');

    $this->actingAs($this->teacher)->getJson('/api/v1/subscription/plans')->assertForbidden();
});

it('refuses a downgrade below current usage with a clear 402', function () {
    (new SubscriptionPlanSeeder)->run();
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'subscription_plan_id' => SubscriptionPlan::where('slug', 'basic')->value('id'), 'status' => 'active']);
    $tiny = SubscriptionPlan::factory()->create(['slug' => 'tiny', 'max_children' => 1]);

    $this->actingAs($this->owner)->postJson('/api/v1/subscription/change-plan', ['subscription_plan_id' => $tiny->id])
        ->assertStatus(402)
        ->assertJsonPath('code', 'plan_limit_reached')
        ->assertJsonPath('errors.limit', 1);
});
