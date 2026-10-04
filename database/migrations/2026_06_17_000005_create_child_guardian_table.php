<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CORE many-to-many relationship: child <-> guardian.
     * A child has many guardians (mother, father, grandparent, driver...),
     * a guardian has many children (siblings). Per-pair permissions live here,
     * NOT on the user account. This is what powers pickup verification and
     * (later) custody handling.
     */
    public function up(): void
    {
        Schema::create('child_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship');             // App\Enums\GuardianRelationship
            $table->string('role')->default('viewer');  // App\Enums\GuardianRole
            $table->boolean('can_view_wall')->default(true);
            $table->boolean('can_pickup')->default(false);
            $table->boolean('is_payer')->default(false);
            $table->string('custody_flag')->default('none'); // App\Enums\CustodyFlag
            $table->json('notify_preferences')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'guardian_id']);
            $table->index(['tenant_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_guardian');
    }
};
