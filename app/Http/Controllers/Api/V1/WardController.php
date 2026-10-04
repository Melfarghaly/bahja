<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChildResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WardController extends Controller
{
    /**
     * The unified siblings view for a guardian: every child they are linked to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $wards = $request->user()
            ->wards()
            ->with('classroom:id,name')
            ->get();

        return ChildResource::collection($wards);
    }
}
