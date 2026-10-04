<?php

namespace Database\Seeders;

use App\Enums\MemberType;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DemoNurserySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'phone' => '01000000001',
            'email' => 'owner@bahga.test',
        ]);

        $tenant = Tenant::factory()->create(['name' => 'Bahga Demo Nursery', 'slug' => 'bahga-demo']);
        $tenant->members()->attach($owner->id, ['member_type' => MemberType::Owner->value, 'status' => 'active']);

        // Set tenant context so scoped models receive tenant_id automatically.
        app(TenantContext::class)->set($tenant);

        $plan = SubscriptionPlan::where('slug', 'basic')->first();
        if ($plan !== null) {
            app(SubscriptionService::class)->startTrial($tenant, $plan);
        }

        $classroom = Classroom::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sunflowers']);

        $mother = User::factory()->create(['name' => 'Demo Mother', 'phone' => '01000000002']);
        $driver = User::factory()->create(['name' => 'Demo Driver', 'phone' => '01000000003']);

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
        }

        app(TenantContext::class)->forget();
    }
}
