<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CORE many-to-many relationship: teacher <-> nursery (tenant).
     * Designed M:N from day one so a teacher can later work across nurseries
     * (Phase 2) and accumulate a portable professional profile (Phase 3).
     */
    public function up(): void
    {
        Schema::create('teacher_nursery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->default('teacher');         // App\Enums\TeacherRole
            $table->string('status')->default('active');        // App\Enums\TeacherStatus
            $table->string('employment_type')->default('full_time'); // App\Enums\EmploymentType
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_nursery');
    }
};
