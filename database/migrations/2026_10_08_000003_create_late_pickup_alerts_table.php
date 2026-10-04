<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Late pickup alerts sent: one per child, per day, per stage (guardians,
 * then managers). The unique key makes the 15-minute job safe to rerun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_pickup_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('stage');                 // guardians / managers
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['child_id', 'date', 'stage']);
            $table->index(['tenant_id', 'date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            DB::statement('alter table late_pickup_alerts enable row level security');
            DB::statement('alter table late_pickup_alerts force row level security');
            DB::statement("create policy tenant_isolation on late_pickup_alerts using ({$condition}) with check ({$condition})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('late_pickup_alerts');
    }
};
