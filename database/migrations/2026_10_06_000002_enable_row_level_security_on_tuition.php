<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extends the deny-by-default RLS policy (see enable_row_level_security) to
 * the Bahga Pay tables: family financial data is as sensitive as child data.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'fee_plans',
        'fee_discounts',
        'child_fee_plans',
        'tuition_invoices',
        'tuition_invoice_items',
        'tuition_payments',
        'ledger_entries',
        'document_sequences',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $condition = "current_setting('app.bypass_rls', true) = 'on'"
            ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

        foreach ($this->tables as $table) {
            DB::statement("alter table {$table} enable row level security");
            DB::statement("alter table {$table} force row level security");
            DB::statement("create policy tenant_isolation on {$table} using ({$condition}) with check ({$condition})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("drop policy if exists tenant_isolation on {$table}");
            DB::statement("alter table {$table} no force row level security");
            DB::statement("alter table {$table} disable row level security");
        }
    }
};
