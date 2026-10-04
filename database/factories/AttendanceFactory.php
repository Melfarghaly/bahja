<?php

namespace Database\Factories;

use App\Enums\AttendanceMethod;
use App\Models\Attendance;
use App\Models\Child;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $child = Child::factory()->create();

        return [
            'tenant_id' => $child->tenant_id,
            'child_id' => $child->id,
            'date' => today(),
            'check_in_method' => AttendanceMethod::Qr,
        ];
    }
}
