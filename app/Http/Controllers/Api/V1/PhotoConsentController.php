<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GuardianRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePhotoConsentRequest;
use App\Models\Child;
use App\Services\Wall\MediaConsentService;
use App\Services\WardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guardian app: may the nursery photograph my child, and show them in group photos?
 */
class PhotoConsentController extends Controller
{
    public function __construct(
        private MediaConsentService $consents,
        private WardService $wards,
    ) {}

    public function show(Request $request, Child $child): JsonResponse
    {
        $ward = $this->wards->find($request->user(), $child);

        return $this->respond($this->consents->status($ward), $ward->pivot->role === GuardianRole::Primary->value);
    }

    public function update(UpdatePhotoConsentRequest $request, Child $child): JsonResponse
    {
        $ward = $this->wards->find($request->user(), $child);
        $choices = collect($request->validated())->map(fn ($value) => (bool) $value)->all();

        return $this->respond($this->consents->update($request->user(), $ward, $choices), true);
    }

    /**
     * @param  array{wall: bool, group_photos: bool}  $status
     */
    private function respond(array $status, bool $canChange): JsonResponse
    {
        return response()->json(['data' => $status + ['can_change' => $canChange]]);
    }
}
