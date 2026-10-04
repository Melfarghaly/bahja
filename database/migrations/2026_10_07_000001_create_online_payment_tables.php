<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online tuition payments (Paymob, Fawry): one payment intent per checkout
 * attempt, and a log of every webhook received, keyed so a gateway retrying
 * the same event can never apply it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuition_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->string('gateway');                             // App\Enums\PaymentGatewayName
            $table->unsignedBigInteger('amount_piasters');
            $table->string('merchant_reference')->unique();        // ours, sent to the gateway
            $table->string('gateway_reference')->nullable();       // theirs (intention / order / Fawry ref)
            $table->text('checkout_url')->nullable();
            $table->string('payment_code')->nullable();            // Fawry reference number shown to the parent
            $table->string('status')->default('pending');          // App\Enums\PaymentIntentStatus
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tuition_invoice_id', 'status']);
        });

        Schema::table('tuition_payments', function (Blueprint $table) {
            // An intent can produce at most one payment.
            $table->foreignId('payment_intent_id')->nullable()->unique()->after('tuition_invoice_id')->constrained()->nullOnDelete();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->string('outcome')->nullable();                 // applied / ignored / duplicate / needs_review / failed
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $condition = "current_setting('app.bypass_rls', true) = 'on'"
                ." OR tenant_id = nullif(current_setting('app.current_tenant', true), '')::bigint";

            DB::statement('alter table payment_intents enable row level security');
            DB::statement('alter table payment_intents force row level security');
            DB::statement("create policy tenant_isolation on payment_intents using ({$condition}) with check ({$condition})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');

        Schema::table('tuition_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_intent_id');
        });

        Schema::dropIfExists('payment_intents');
    }
};
