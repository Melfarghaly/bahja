<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profiles) {}

    public function show(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user(), $this->profiles->nurseries($request->user()));
    }
}
