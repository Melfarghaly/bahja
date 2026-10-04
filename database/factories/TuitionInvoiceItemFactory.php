<?php

namespace Database\Factories;

use App\Enums\InvoiceItemKind;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\TuitionInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TuitionInvoiceItem>
 */
class TuitionInvoiceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tuition_invoice_id' => TuitionInvoice::factory(),
            'kind' => InvoiceItemKind::Fee,
            'description' => 'المصروفات الشهرية',
            'amount_piasters' => 150_000,
        ];
    }
}
