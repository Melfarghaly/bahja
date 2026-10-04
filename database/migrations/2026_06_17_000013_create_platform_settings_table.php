<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-row table holding platform-wide branding (visual identity, logo,
     * colors) controlled by the super admin.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name')->default('Bahga');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->default('#F26A4F');
            $table->string('secondary_color', 9)->default('#134E4A');
            $table->string('accent_color', 9)->default('#14B8A6');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
