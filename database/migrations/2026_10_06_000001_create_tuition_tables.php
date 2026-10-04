<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bahga Pay — tuition billing (families pay the nursery). Entirely separate from
 * the SaaS `invoices` table (the nursery pays Bahga). All money is integer
 * piasters (bigint). Never floats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_piasters');
            $table->string('frequency');                         // App\Enums\FeeFrequency
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');                              // App\Enums\DiscountType
            $table->string('value_type');                        // App\Enums\DiscountValueType
            $table->unsignedBigInteger('value');                 // basis points or piasters
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
        });

        Schema::create('child_fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'child_id']);
        });

        Schema::create('tuition_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->date('period_start');                        // first day of the billed month
            $table->date('issued_on');
            $table->date('due_on');
            $table->unsignedBigInteger('subtotal_piasters');
            $table->unsignedBigInteger('discount_piasters')->default(0);
            $table->unsignedBigInteger('total_piasters');
            $table->unsignedBigInteger('paid_piasters')->default(0); // cache of non-void payments
            $table->string('status')->default('open');           // App\Enums\TuitionInvoiceStatus
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            // One family invoice per payer per period: makes generation idempotent.
            $table->unique(['tenant_id', 'payer_id', 'period_start']);
            $table->index(['tenant_id', 'status', 'due_on']);
        });

        Schema::create('tuition_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuition_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');                              // App\Enums\InvoiceItemKind
            $table->string('description');
            $table->bigInteger('amount_piasters');               // negative for discounts
            $table->timestamps();

            $table->index(['tenant_id', 'tuition_invoice_id']);
        });

        Schema::create('tuition_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tuition_invoice_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number');
            $table->string('method');                            // App\Enums\TuitionPaymentMethod
            $table->unsignedBigInteger('amount_piasters');
            $table->string('reference')->nullable();             // transfer ref, InstaPay ref…
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'paid_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('transaction_id');                      // groups the balanced lines of one posting
            $table->string('account');                           // App\Enums\LedgerAccount
            $table->unsignedBigInteger('debit_piasters')->default(0);
            $table->unsignedBigInteger('credit_piasters')->default(0);
            $table->foreignId('tuition_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tuition_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->timestamp('posted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'account', 'posted_at']);
            $table->index(['tenant_id', 'transaction_id']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');                               // e.g. "INV-2026", "RCT-2026"
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('tuition_payments');
        Schema::dropIfExists('tuition_invoice_items');
        Schema::dropIfExists('tuition_invoices');
        Schema::dropIfExists('child_fee_plans');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_plans');
    }
};
