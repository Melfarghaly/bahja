<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders sent (or deliberately skipped) for unpaid tuition invoices.
 * One row per invoice per step: the unique key guarantees a parent never
 * gets the same reminder twice, even if the daily job runs again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuition_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('step');                                // App\Enums\DunningStep
            $table->string('channel');                             // sms / escalation
            $table->string('status');                              // sent / skipped / failed
            $table->string('recipient_phone')->nullable();
            $table->text('message')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('skip_reason')->nullable();
            $table->timestamps();

            $table->unique(['tuition_invoice_id', 'step']);
            $table->index(['tenant_id', 'step', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            DB::statement('alter table dunning_notices enable row level security');
            DB::statement('alter table dunning_notices force row level security');
            DB::statement("create policy tenant_isolation on dunning_notices using ({$condition}) with check ({$condition})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_notices');
    }
};
