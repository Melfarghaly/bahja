<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachGuardianRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use App\Models\User;
use App\Services\GuardianService;

class GuardianController extends Controller
{
    public function __construct(private GuardianService $guardians) {}

    public function store(AttachGuardianRequest $request, Child $child): ChildResource
    {
        $guardian = User::findOrFail($request->validated('user_id'));

        $this->guardians->attach($child, $guardian, $request->validated());

        return new ChildResource($child->load('guardians'));
    }

    public function destroy(Child $child, User $guardian): ChildResource
    {
        $this->authorize('update', $child);

        $this->guardians->detach($child, $guardian);

        return new ChildResource($child->load('guardians'));
    }
}
