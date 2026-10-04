<?php

namespace App\Services\Pickup;

use App\Enums\CustodyFlag;
use App\Enums\PickupMethod;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pickup\PickupCandidate;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Identifies who is collecting and decides, child by child, whether they may.
 * A custody block always wins; a pass is valid only for its own child, inside
 * its window, once.
 */
class PickupVerificationService
{
    public const OK = null;

    public const CUSTODY_BLOCKED = 'custody_blocked';

    public const NOT_AUTHORIZED = 'not_authorized';

    public function __construct(
        private PickupTokenService $tokens,
        private PickupPassService $passes,
    ) {}

    /**
     * @param  array{pickup_token?: ?string, pass_code?: ?string}  $input
     *
     * @throws ValidationException when the QR / code is invalid or expired.
     */
    public function identify(Tenant $tenant, array $input): PickupCandidate
    {
        if (filled($input['pickup_token'] ?? null)) {
            $guardianId = $this->tokens->guardianId($input['pickup_token'], $tenant);
            $guardian = $guardianId !== null ? User::find($guardianId) : null;

            if ($guardian === null) {
                throw ValidationException::withMessages(['pickup_token' => __('pickup.invalid_token')]);
            }

            return new PickupCandidate(PickupMethod::DynamicQr, $guardian->name, $guardian->phone, user: $guardian);
        }

        $pass = $this->passes->findUsable($tenant, (string) ($input['pass_code'] ?? ''));

        if ($pass === null) {
            throw ValidationException::withMessages(['pass_code' => __('pickup.invalid_pass')]);
        }

        return new PickupCandidate(PickupMethod::PassCode, $pass->name, $pass->phone, pass: $pass);
    }

    public function forGuardian(User $guardian): PickupCandidate
    {
        return new PickupCandidate(PickupMethod::Guardian, $guardian->name, $guardian->phone, user: $guardian);
    }

    /**
     * Null when allowed, otherwise the reason code.
     */
    public function refusal(PickupCandidate $candidate, Child $child): ?string
    {
        if ($candidate->pass !== null) {
            return $candidate->pass->child_id === $child->id ? self::OK : self::NOT_AUTHORIZED;
        }

        $link = $child->guardians()->whereKey($candidate->user->id)->first()?->pivot;

        return match (true) {
            $link === null => self::NOT_AUTHORIZED,
            $link->custody_flag === CustodyFlag::Blocked->value => self::CUSTODY_BLOCKED,
            ! $link->can_pickup => self::NOT_AUTHORIZED,
            default => self::OK,
        };
    }

    /**
     * The children this person could be here for: a guardian's wards in the
     * nursery (blocked links included, so staff see the red flag), or the
     * pass's child.
     *
     * @return Collection<int, Child>
     */
    public function childrenFor(PickupCandidate $candidate): Collection
    {
        $relations = [
            'classroom:id,name',
            'attendances' => fn ($q) => $q->whereDate('date', today()),
        ];

        return $candidate->pass !== null
            ? Child::whereKey($candidate->pass->child_id)->with($relations)->get()
            : $candidate->user->wards()->with($relations)->orderBy('first_name')->get();
    }
}
