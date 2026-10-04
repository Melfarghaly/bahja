<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Global catalog of plans (NOT tenant scoped).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_egp');           // 0 / 750 / 1900 / 3900
            $table->string('billing_cycle')->default('monthly'); // App\Enums\BillingCycle
            $table->unsignedInteger('max_children')->nullable(); // null = unlimited
            $table->unsignedInteger('max_teachers')->nullable();
            $table->unsignedInteger('included_sms')->default(0);
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
