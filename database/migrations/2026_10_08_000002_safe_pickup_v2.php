<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe Pickup 2.0: one-time pickup passes for people without a Bahga account
 * (a driver, an aunt), and a record of *how* each pickup was verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('code_hash');                     // HMAC of the 6-digit code
            $table->string('note')->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'code_hash']);
            $table->index(['tenant_id', 'child_id', 'valid_until']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('pickup_method')->nullable()->after('pickup_verified');       // App\Enums\PickupMethod
            $table->foreignId('pickup_pass_id')->nullable()->after('pickup_method')->constrained()->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
            $table->string('override_reason')->nullable()->after('pickup_pass_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            DB::statement('alter table pickup_passes enable row level security');
            DB::statement('alter table pickup_passes force row level security');
            DB::statement("create policy tenant_isolation on pickup_passes using ({$condition}) with check ({$condition})");
        }
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_pass_id');
            $table->dropConstrainedForeignId('checked_out_by');
            $table->dropColumn(['pickup_method', 'override_reason']);
        });

        Schema::dropIfExists('pickup_passes');
    }
};
