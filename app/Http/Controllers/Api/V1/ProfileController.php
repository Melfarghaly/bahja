<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\ProfileService;
use App\Services\ProfileUpdateService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profiles,
        private ProfileUpdateService $updates,
    ) {}

    public function show(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user(), $this->profiles->nurseries($request->user()));
    }

    public function update(UpdateProfileRequest $request): UserProfileResource
    {
        $user = $this->updates->update($request->user(), $request->validated());

        return new UserProfileResource($user, $this->profiles->nurseries($user));
    }
}
