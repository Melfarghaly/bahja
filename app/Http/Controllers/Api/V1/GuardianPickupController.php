<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssuePickupPassRequest;
use App\Http\Resources\PickupPassResource;
use App\Models\Child;
use App\Models\PickupPass;
use App\Services\Pickup\PickupPassService;
use App\Services\Pickup\PickupTokenService;
use App\Services\Pickup\PickupVerificationService;
use App\Services\WardService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Guardian app: my rotating pickup QR, and one-time passes for people who
 * collect my child on my behalf.
 */
class GuardianPickupController extends Controller
{
    public function __construct(
        private PickupTokenService $tokens,
        private PickupPassService $passes,
        private PickupVerificationService $verification,
        private WardService $wards,
        private TenantContext $tenantContext,
    ) {}

    public function code(Request $request): JsonResponse
    {
        $user = $request->user();
        $candidate = $this->verification->forGuardian($user);

        $canPickUpSomeone = $this->wards->list($user)
            ->contains(fn (Child $child) => $this->verification->refusal($candidate, $child) === null);

        abort_unless($canPickUpSomeone, 403, __('pickup.no_pickup_rights'));

        $issued = $this->tokens->issue($user, $this->tenantContext->get());

        return response()->json(['data' => [
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at']->toIso8601String(),
            'refresh_after' => PickupTokenService::REFRESH_SECONDS,
        ]]);
    }

    public function index(Request $request, Child $child): AnonymousResourceCollection
    {
        $ward = $this->wards->find($request->user(), $child);

        return PickupPassResource::collection($this->passes->forChild($ward));
    }

    public function store(IssuePickupPassRequest $request, Child $child): JsonResponse
    {
        $ward = $this->authorizedWard($request, $child);
        $issued = $this->passes->issue($request->user(), $ward, $request->validated());

        // The code is shown once (it was also sent to the delegate by SMS).
        return (new PickupPassResource($issued['pass']))
            ->additional(['code' => $issued['code']])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, PickupPass $pass): PickupPassResource
    {
        $this->authorizedWard($request, $pass->child);

        return new PickupPassResource($this->passes->revoke($pass, $request->user()));
    }

    /**
     * Only a guardian who may pick the child up may delegate that right.
     */
    private function authorizedWard(Request $request, Child $child): Child
    {
        $ward = $this->wards->find($request->user(), $child);

        abort_unless(
            $this->verification->refusal($this->verification->forGuardian($request->user()), $ward) === null,
            403,
            __('pickup.no_pickup_rights'),
        );

        return $ward;
    }
}
