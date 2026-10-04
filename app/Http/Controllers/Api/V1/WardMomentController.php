<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MomentResource;
use App\Models\Child;
use App\Models\Moment;
use App\Services\Wall\MomentFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Guardian app: my child's Daily Wall.
 */
class WardMomentController extends Controller
{
    public function __construct(private MomentFeedService $feed) {}

    public function index(Request $request, Child $child): AnonymousResourceCollection
    {
        return MomentResource::collection($this->feed->forWard($request->user(), $child));
    }

    public function acknowledge(Request $request, Child $child, Moment $moment): MomentResource
    {
        return new MomentResource($this->feed->acknowledge($request->user(), $child, $moment));
    }
}
