<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@bahga.test'],
            [
                'name' => 'Platform Admin',
                'phone' => '01000000000',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );
    }
}
