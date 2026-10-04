<?php

namespace Database\Seeders;

use App\Enums\CouponDuration;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class FoundersCouponSeeder extends Seeder
{
    /**
     * "Founding Fifty": the first 50 nurseries keep a lifetime discount on any paid plan.
     */
    public function run(): void
    {
        Coupon::firstOrCreate(['code' => 'FOUNDERS50'], [
            'name' => 'الخمسون المؤسِّسون',
            'percent_off' => 35,
            'duration' => CouponDuration::Forever,
            'max_redemptions' => 50,
            'is_active' => true,
        ]);
    }
}
