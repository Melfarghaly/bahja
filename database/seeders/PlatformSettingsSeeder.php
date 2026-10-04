<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::firstOrCreate([], [
            'brand_name' => 'Bahga',
            'primary_color' => '#F26A4F',
            'secondary_color' => '#134E4A',
            'accent_color' => '#14B8A6',
            'support_email' => 'support@bahga.test',
        ]);
    }
}
