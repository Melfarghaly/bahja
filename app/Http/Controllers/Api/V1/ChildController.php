<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListChildrenRequest;
use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use App\Services\ChildService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChildController extends Controller
{
    public function __construct(private ChildService $children) {}

    public function index(ListChildrenRequest $request): AnonymousResourceCollection
    {
        $children = Child::query()
            ->with([
                'classroom:id,name,capacity',
                'guardians:id,name,phone',
                'mediaConsents' => fn ($query) => $query->active(),
                'attendances' => fn ($query) => $query->whereDate('date', today())->with('pickedUpBy:id,name'),
            ])
            ->where('status', $request->validated('status') ?? 'active')
            ->when($request->validated('classroom_id'), fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($request->validated('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->orderBy('first_name')
            ->paginate($request->validated('per_page') ?? 20)
            ->withQueryString();

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

        return new ChildResource($child->load([
            'classroom',
            'guardians',
            'mediaConsents' => fn ($q) => $q->active(),
            'attendances' => fn ($q) => $q->whereDate('date', today())->with('pickedUpBy:id,name'),
        ]));
    }

    public function update(UpdateChildRequest $request, Child $child): ChildResource
    {
        $child = $this->children->update($child, $request->validated());

        return new ChildResource($child);
    }
}
