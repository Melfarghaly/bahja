<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Services\OtpLoginService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(private OtpLoginService $otp) {}

    public function store(RequestOtpRequest $request): JsonResponse
    {
        $this->otp->request($request->validated('phone'), $request->ip());

        return response()->json([
            'message' => __('otp.sent'),
            'expires_in' => OtpLoginService::TTL_MINUTES * 60,
            'resend_after' => OtpLoginService::RESEND_SECONDS,
        ], 202);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $token = $this->otp->verify(
            $request->validated('phone'),
            $request->validated('code'),
            $request->validated('device_name'),
        );

        return response()->json(['token' => $token, 'token_type' => 'Bearer'], 201);
    }
}
