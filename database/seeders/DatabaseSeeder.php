<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformSettingsSeeder::class,
            SuperAdminSeeder::class,
            SubscriptionPlanSeeder::class,
            DemoNurserySeeder::class,
        ]);
    }
}
