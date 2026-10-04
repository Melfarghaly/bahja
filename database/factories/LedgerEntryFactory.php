<?php

namespace Database\Factories;

use App\Enums\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'transaction_id' => (string) Str::uuid(),
            'account' => LedgerAccount::Cash,
            'debit_piasters' => 10_000,
            'credit_piasters' => 0,
            'description' => 'Test posting',
            'posted_at' => now(),
        ];
    }
}
