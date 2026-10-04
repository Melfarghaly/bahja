<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use App\Services\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless sign-in for parents: a 6-digit code by SMS, valid 5 minutes,
 * 5 attempts. Requesting a code never reveals whether a number is registered.
 */
class OtpLoginService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const MAX_PER_HOUR = 5;

    public function __construct(
        private SmsGateway $sms,
        private ApiTokenService $tokens,
    ) {}

    /**
     * @throws ValidationException when asked again too soon (cooldown / hourly cap).
     */
    public function request(string $phone, ?string $ip = null): void
    {
        $phone = PhoneNumber::normalize($phone);

        $this->throttle("otp-send:{$phone}", self::MAX_PER_HOUR, 3600);

        $last = LoginCode::where('phone', $phone)->latest('id')->first();
        if ($last !== null && $last->created_at->diffInSeconds(now()) < self::RESEND_SECONDS) {
            throw ValidationException::withMessages([
                'phone' => __('otp.wait', ['seconds' => self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now())]),
            ]);
        }

        // Unknown numbers get the same answer, but no SMS (no enumeration, no cost).
        if (! User::where('phone', $phone)->exists()) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        LoginCode::create([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        $this->sms->send($phone, __('otp.sms', ['code' => $code, 'minutes' => self::TTL_MINUTES]));
    }

    /**
     * @throws ValidationException on a wrong, expired or exhausted code.
     */
    public function verify(string $phone, string $code, string $deviceName): string
    {
        $phone = PhoneNumber::normalize($phone);

        // Decide inside the lock, but throw only after commit: a failed attempt
        // must be persisted (an exception inside the transaction would roll the
        // attempt counter back and allow unlimited guesses).
        $outcome = DB::transaction(function () use ($phone, $code) {
            $login = LoginCode::where('phone', $phone)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($login === null || $login->attempts >= self::MAX_ATTEMPTS) {
                return 'expired';
            }

            if (! hash_equals($login->code_hash, $this->hash($phone, $code))) {
                $login->increment('attempts');

                return 'invalid';
            }

            $login->update(['consumed_at' => now()]);

            return 'ok';
        });

        if ($outcome !== 'ok') {
            throw ValidationException::withMessages(['code' => __('otp.'.$outcome)]);
        }

        $user = User::where('phone', $phone)->firstOrFail();
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now()])->save();

        return $this->tokens->issueFor($user, $deviceName);
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    /**
     * @throws ValidationException
     */
    private function throttle(string $key, int $max, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'phone' => __('otp.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
