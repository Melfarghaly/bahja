<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VerifyPickupRequest;
use App\Http\Resources\PickupCheckResource;
use App\Services\Pickup\PickupVerificationService;
use App\Support\TenantContext;

/**
 * Staff: scan a guardian's QR or type a pass code, see who it is and which
 * children they may take — before pressing check-out.
 */
class PickupController extends Controller
{
    public function __construct(
        private PickupVerificationService $verification,
        private TenantContext $tenantContext,
    ) {}

    public function verify(VerifyPickupRequest $request): PickupCheckResource
    {
        $candidate = $this->verification->identify($this->tenantContext->get(), $request->validated());

        return new PickupCheckResource($candidate, $this->verification->childrenFor($candidate), $this->verification);
    }
}
