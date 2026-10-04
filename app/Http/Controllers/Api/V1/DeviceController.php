<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgetDeviceRequest;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Http\Resources\PushDeviceResource;
use App\Services\Notifications\PushDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The app registers its push token after sign-in and whenever FCM rotates it.
 */
class DeviceController extends Controller
{
    public function __construct(private PushDeviceService $devices) {}

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        $registered = $this->devices->register(
            $request->user(),
            $accessToken instanceof PersonalAccessToken ? $accessToken->id : null,
            $request->validated(),
        );

        return (new PushDeviceResource($registered['device']))
            ->response()
            ->setStatusCode($registered['created'] ? 201 : 200);
    }

    public function destroy(ForgetDeviceRequest $request): Response
    {
        $this->devices->forget($request->user(), $request->validated('token'));

        return response()->noContent();
    }
}
