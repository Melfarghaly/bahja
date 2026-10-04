<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use App\Services\ChildService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChildController extends Controller
{
    public function __construct(private ChildService $children) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Child::class);

        $children = Child::query()
            ->with([
                'classroom:id,name,capacity',
                'guardians:id,name,phone',
                'attendances' => fn ($query) => $query->whereDate('date', today()),
            ])
            ->where('status', 'active')
            ->paginate(20);

        return ChildResource::collection($children);
    }

    public function store(StoreChildRequest $request): ChildResource
    {
        $child = $this->children->create($request->validated());

        return new ChildResource($child);
    }

    public function show(Child $child): ChildResource
    {
        $this->authorize('view', $child);

        return new ChildResource($child->load(['classroom', 'guardians']));
    }

    public function update(UpdateChildRequest $request, Child $child): ChildResource
    {
        $child = $this->children->update($child, $request->validated());

        return new ChildResource($child);
    }
}
