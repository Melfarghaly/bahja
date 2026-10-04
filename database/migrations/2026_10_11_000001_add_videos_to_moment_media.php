<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short videos on the Daily Wall. A video keeps its file as uploaded (the app
 * compresses it first) and gets an optional poster image as `thumb_path`.
 *
 * moments.client_ref: the app's own id for an update, so a retry after a
 * lost response (offline outbox) never posts the same update twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->uuid('client_ref')->nullable()->after('author_id');
            $table->unique(['tenant_id', 'client_ref']);
        });

        Schema::table('moment_media', function (Blueprint $table) {
            $table->string('kind', 8)->default('photo')->after('moment_id');   // photo / video
            $table->unsignedInteger('duration_ms')->nullable()->after('height');
            $table->string('thumb_path')->nullable()->change();
            $table->unsignedSmallInteger('width')->nullable()->change();
            $table->unsignedSmallInteger('height')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'client_ref']);
            $table->dropColumn('client_ref');
        });

        Schema::table('moment_media', function (Blueprint $table) {
            $table->dropColumn(['kind', 'duration_ms']);
        });
    }
};
