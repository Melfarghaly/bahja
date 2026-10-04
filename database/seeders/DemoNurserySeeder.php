<?php

namespace Database\Seeders;

use App\Enums\MemberType;
use App\Enums\RolloutFlag;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\Classroom;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Laravel\Pennant\Feature;

class DemoNurserySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'phone' => '01000000001',
            'email' => 'owner@bahga.test',
        ]);

        $tenant = Tenant::factory()->create(['name' => 'Bahga Demo Nursery', 'slug' => 'bahga-demo', 'settings' => ['pickup_deadline' => '16:00']]);
        $tenant->members()->attach($owner->id, ['member_type' => MemberType::Owner->value, 'status' => 'active']);

        // Set tenant context so scoped models receive tenant_id automatically.
        app(TenantContext::class)->set($tenant);

        // Pro trial: includes Auto Collection (online payments, reminders).
        $plan = SubscriptionPlan::where('slug', 'pro')->first();
        if ($plan !== null) {
            app(SubscriptionService::class)->startTrial($tenant, $plan);
        }

        $classroom = Classroom::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sunflowers']);

        $teacher = User::factory()->create(['name' => 'Demo Teacher', 'phone' => '01000000004', 'email' => 'teacher@bahga.test']);
        $teacher->nurseriesAsTeacher()->attach($tenant->id, [
            'role' => 'teacher', 'status' => 'active', 'employment_type' => 'full_time',
            'classroom_id' => $classroom->id, 'joined_at' => now(),
        ]);
        $tenant->members()->attach($teacher->id, ['member_type' => MemberType::Teacher->value, 'status' => 'active']);

        $mother = User::factory()->create(['name' => 'Demo Mother', 'phone' => '01000000002', 'email' => 'mother@bahga.test']);
        $driver = User::factory()->create(['name' => 'Demo Driver', 'phone' => '01000000003']);

        FeeDiscount::create(['name' => 'خصم الإخوة', 'type' => 'sibling', 'value_type' => 'percent', 'value' => 1_000, 'is_active' => true]);
        $monthly = FeePlan::create(['name' => 'المصروفات الشهرية', 'amount_piasters' => 185_000, 'frequency' => 'monthly', 'is_active' => true]);

        // Two siblings sharing the same mother (demonstrates the M:N + unified view).
        foreach (['Yousef', 'Layla'] as $name) {
            $child = Child::factory()->create([
                'tenant_id' => $tenant->id,
                'classroom_id' => $classroom->id,
                'first_name' => $name,
            ]);

            $child->guardians()->attach($mother->id, [
                'tenant_id' => $tenant->id,
                'relationship' => 'mother',
                'role' => 'primary',
                'can_view_wall' => true,
                'can_pickup' => true,
                'is_payer' => true,
            ]);

            $child->guardians()->attach($driver->id, [
                'tenant_id' => $tenant->id,
                'relationship' => 'driver',
                'role' => 'pickup_authorized',
                'can_view_wall' => false,
                'can_pickup' => true,
            ]);

            ChildFeePlan::create(['child_id' => $child->id, 'fee_plan_id' => $monthly->id, 'starts_on' => now()->startOfMonth()]);
        }

        foreach ([$mother, $driver] as $guardian) {
            $tenant->members()->syncWithoutDetaching([$guardian->id => ['member_type' => MemberType::Guardian->value, 'status' => 'active']]);
        }

        // Bahga Pay released for the demo nursery, with this month's family invoice.
        Feature::for($tenant)->activate(RolloutFlag::BahgaPay->value);
        app(TuitionBillingService::class)->generate($tenant, CarbonImmutable::now()->startOfMonth(), $owner);

        // Safe Pickup 2.0 too: rotating guardian QR, pickup passes, late pickup alerts.
        Feature::for($tenant)->activate(RolloutFlag::SafePickupV2->value);

        // Messaging Hub: arrival / pickup notifications to the family.
        Feature::for($tenant)->activate(RolloutFlag::MessagingHub->value);

        app(TenantContext::class)->forget();
    }
}
