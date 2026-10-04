<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entitlements = plan features/limits + purchased add-ons + per-tenant
 * overrides (founder deals, enterprise contracts, support goodwill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Extra quotas beyond max_children / max_teachers, keyed by App\Enums\Limit.
            $table->json('limits')->nullable()->after('features');
        });

        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('addon');                          // App\Enums\Addon
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'addon']);
        });

        Schema::create('tenant_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');                            // App\Enums\Feature or App\Enums\Limit value
            $table->json('value')->nullable();                // bool for features; int, or null = unlimited, for limits
            $table->string('reason')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlement_overrides');
        Schema::dropIfExists('tenant_addons');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('limits');
        });
    }
};
