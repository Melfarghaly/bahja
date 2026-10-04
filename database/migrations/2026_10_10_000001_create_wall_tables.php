<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Daily Wall:
 * - moments: one update by a teacher (photo, meal, nap, ...), for one or
 *   many children at once;
 * - moment_child: who it is about — the ONLY thing that decides which
 *   families see it (plus the incident acknowledgement per child);
 * - moment_media: re-encoded photos on a private disk, served by signed URLs;
 * - media_consents: a family's permission to photograph the child (wall)
 *   and to show the child in group photos other families see.
 */
return new class extends Migration
{
    private const TABLES = ['moments', 'moment_child', 'moment_media', 'media_consents'];

    public function up(): void
    {
        Schema::create('moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('children_count')->default(0);
            $table->unsignedSmallInteger('media_count')->default(0);
            $table->boolean('requires_ack')->default(false);
            $table->timestamp('published_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'published_at']);
        });

        Schema::create('moment_child', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['moment_id', 'child_id']);
            $table->index(['child_id', 'moment_id']);
        });

        Schema::create('moment_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moment_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('thumb_path');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('media_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);                 // wall / group_photos
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['child_id', 'scope', 'revoked_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            foreach (self::TABLES as $table) {
                DB::statement("alter table {$table} enable row level security");
                DB::statement("alter table {$table} force row level security");
                DB::statement("create policy tenant_isolation on {$table} using ({$condition}) with check ({$condition})");
            }

            // At most one active consent per child and scope.
            DB::statement('create unique index media_consents_active_unique on media_consents (child_id, scope) where revoked_at is null');
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
