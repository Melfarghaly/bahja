<?php

namespace Database\Factories;

use App\Enums\DunningStep;
use App\Models\DunningNotice;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DunningNotice>
 */
class DunningNoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tuition_invoice_id' => TuitionInvoice::factory(),
            'step' => DunningStep::DueDay,
            'channel' => 'sms',
            'status' => DunningNotice::SENT,
        ];
    }
}
