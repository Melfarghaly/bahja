<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Second isolation layer (defense in depth): PostgreSQL Row-Level Security on
 * the tables holding children's data. Deny-by-default — a row is visible only
 * when the session is pinned to its tenant, or when platform-wide work has
 * explicitly enabled the bypass. FORCE applies the policy to the table owner
 * too; only superusers / BYPASSRLS roles skip it, so the app must never
 * connect as one. See App\Support\RowLevelSecurity.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'children',
        'child_guardian',
        'classrooms',
        'attendances',
        'audit_logs',
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
