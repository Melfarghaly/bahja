<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tenant is a single nursery. It is the root of all tenant-scoped data.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();           // Used as subdomain.
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('trial'); // App\Enums\TenantStatus
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
