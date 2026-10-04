<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notification Router:
 * - push_devices: a user's app installs (FCM tokens). Global, like users;
 *   tied to the Sanctum token that registered it, so logging out of a
 *   device stops its notifications.
 * - user_notifications: the in-app inbox, per nursery. Text is rendered
 *   from `type` + `params` in the reader's language.
 * - notification_deliveries: every push / SMS attempt, for support and cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 16);                 // android / ios / web
            $table->string('token', 512)->unique();
            $table->string('locale', 5)->default('ar');
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('params')->nullable();
            $table->json('data')->nullable();               // deep link for the app
            $table->string('push_status', 16)->default('pending');
            $table->timestamp('deliver_after')->nullable(); // deferred by quiet hours
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id', 'read_at']);
            $table->index(['push_status', 'deliver_after']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 8);                   // push / sms
            $table->string('status', 16);                   // sent / failed
            $table->string('provider_ref')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            foreach (['user_notifications', 'notification_deliveries'] as $table) {
                DB::statement("alter table {$table} enable row level security");
                DB::statement("alter table {$table} force row level security");
                DB::statement("create policy tenant_isolation on {$table} using ({$condition}) with check ({$condition})");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('push_devices');
    }
};
