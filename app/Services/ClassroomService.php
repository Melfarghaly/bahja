<?php

namespace App\Services;

use App\Models\Classroom;

/**
 * CRUD for classrooms within the current nursery. tenant_id is assigned
 * automatically by the BelongsToTenant trait.
 */
class ClassroomService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Classroom
    {
        return Classroom::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Classroom $classroom, array $data): Classroom
    {
        $classroom->update($data);

        return $classroom;
    }

    public function delete(Classroom $classroom): void
    {
        // Detach children from the classroom before removing it (keep the children).
        $classroom->children()->update(['classroom_id' => null]);
        $classroom->delete();
    }
}
