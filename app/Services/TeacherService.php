<?php

namespace App\Services;

use App\Enums\MemberType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Manages teacher membership within the current nursery (the teacher_nursery M:N).
 */
class TeacherService
{
    public function __construct(
        private UserDirectoryService $directory,
        private SubscriptionService $subscriptions,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = $this->tenantContext->get();

            $teacher = $this->directory->findOrCreateByPhone($data['phone'], [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
            ]);

            // Only a new staff member consumes quota; re-saving an existing one doesn't.
            if (! $teacher->teachesIn($tenant)) {
                $this->subscriptions->assertCanAddStaff($tenant);
            }

            $teacher->nurseriesAsTeacher()->syncWithoutDetaching([
                $tenant->id => [
                    'role' => $data['role'],
                    'status' => $data['status'] ?? 'active',
                    'employment_type' => $data['employment_type'] ?? 'full_time',
                    'classroom_id' => $data['classroom_id'] ?? null,
                    'joined_at' => now(),
                ],
            ]);

            // Also record a tenant membership so the teacher can sign in to this nursery.
            $tenant->members()->syncWithoutDetaching([
                $teacher->id => ['member_type' => MemberType::Teacher->value, 'status' => 'active'],
            ]);

            return $teacher;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $teacher, array $data): User
    {
        $tenant = $this->tenantContext->get();

        $teacher->nurseriesAsTeacher()->updateExistingPivot($tenant->id, [
            'role' => $data['role'],
            'status' => $data['status'],
            'employment_type' => $data['employment_type'],
            'classroom_id' => $data['classroom_id'] ?? null,
        ]);

        return $teacher;
    }

    public function remove(User $teacher): void
    {
        $tenant = $this->tenantContext->get();

        $teacher->nurseriesAsTeacher()->detach($tenant->id);
        $tenant->members()->wherePivot('member_type', MemberType::Teacher->value)->detach($teacher->id);
    }
}
