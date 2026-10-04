<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount coupons on the SaaS subscription (e.g. "Founding Fifty": a lifetime
 * discount for the first 50 nurseries). Coupons are platform-wide; each
 * redemption belongs to one nursery and snapshots the discount it got.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // stored upper-case
            $table->string('name');
            $table->unsignedTinyInteger('percent_off');       // 1..100
            $table->string('duration');                       // App\Enums\CouponDuration
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable(); // null = unlimited
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->json('plan_slugs')->nullable();           // null = any paid plan
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('percent_off');       // snapshot at redemption
            $table->timestamp('discount_ends_at')->nullable(); // null = forever
            $table->timestamps();

            $table->unique(['tenant_id', 'coupon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
