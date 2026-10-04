<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Membership of a global user inside a tenant, plus the high-level role
     * the user holds within that tenant.
     */
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('member_type');              // App\Enums\MemberType
            $table->string('status')->default('active'); // App\Enums\MembershipStatus
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'member_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
