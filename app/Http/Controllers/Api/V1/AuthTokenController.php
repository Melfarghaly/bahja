<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthTokenController extends Controller
{
    public function __construct(private ApiTokenService $tokens) {}

    public function store(IssueTokenRequest $request): JsonResponse
    {
        $token = $this->tokens->issue(
            $request->validated('login'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return response()->json(['token' => $token, 'token_type' => 'Bearer'], 201);
    }

    public function destroy(Request $request): Response
    {
        $this->tokens->revoke($request->user());

        return response()->noContent();
    }
}
