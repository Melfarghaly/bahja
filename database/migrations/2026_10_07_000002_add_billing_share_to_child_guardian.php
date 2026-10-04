<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split a child's fees between several paying guardians (e.g. separated
 * parents 60/40). Basis points; null = not specified (equal split).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_guardian', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_share_bp')->nullable()->after('is_payer');
        });
    }

    public function down(): void
    {
        Schema::table('child_guardian', function (Blueprint $table) {
            $table->dropColumn('billing_share_bp');
        });
    }
};
