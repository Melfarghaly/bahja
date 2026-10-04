<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Child;
use App\Models\Classroom;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Child::class);

        return ClassroomResource::collection(
            Classroom::withCount(['children' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get(),
        );
    }
}
