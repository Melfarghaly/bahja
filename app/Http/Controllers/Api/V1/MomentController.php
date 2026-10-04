<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListMomentsRequest;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Http\Resources\MomentResource;
use App\Models\Moment;
use App\Services\Wall\MomentFeedService;
use App\Services\Wall\MomentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff: post to the Daily Wall and browse it.
 */
class MomentController extends Controller
{
    public function __construct(
        private MomentService $moments,
        private MomentFeedService $feed,
        private TenantContext $tenantContext,
    ) {}

    public function index(ListMomentsRequest $request): AnonymousResourceCollection
    {
        return MomentResource::collection($this->feed->forStaff($request->validated()));
    }

    public function store(StoreMomentRequest $request): JsonResponse
    {
        $moment = $this->moments->post(
            $this->tenantContext->get(),
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', []),
        );

        return (new MomentResource($moment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Moment $moment): Response
    {
        abort_unless($request->user()->can('delete', $moment), 403, __('wall.delete_window'));

        $this->moments->delete($moment, $request->user());

        return response()->noContent();
    }
}
