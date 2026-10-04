<?php

namespace App\Services\Pickup;

use App\Enums\AuditAction;
use App\Models\Child;
use App\Models\PickupPass;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-time pickup passes: a guardian authorizes someone without a Bahga
 * account (a driver, an aunt) to collect one child within a time window. The
 * delegate receives a 6-digit code by SMS; only its HMAC is stored.
 */
class PickupPassService
{
    public const MAX_HOURS = 24;

    public function __construct(
        private SmsGateway $sms,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, phone: string, valid_from?: ?string, valid_until: string, note?: ?string}  $data
     * @return array{pass: PickupPass, code: string}
     */
    public function issue(User $guardian, Child $child, array $data): array
    {
        $tenant = Tenant::findOrFail($child->tenant_id);
        $phone = PhoneNumber::normalize($data['phone']);

        [$pass, $code] = DB::transaction(function () use ($guardian, $child, $data, $tenant, $phone) {
            $code = $this->uniqueCode($tenant);

            $pass = PickupPass::create([
                'tenant_id' => $child->tenant_id,
                'child_id' => $child->id,
                'issued_by' => $guardian->id,
                'name' => $data['name'],
                'phone' => $phone,
                'code_hash' => $this->hash($tenant, $code),
                'note' => $data['note'] ?? null,
                'valid_from' => isset($data['valid_from']) ? CarbonImmutable::parse($data['valid_from']) : now(),
                'valid_until' => CarbonImmutable::parse($data['valid_until']),
            ]);

            $this->audit->record(AuditAction::PickupPassIssued, $child, [
                'pickup_pass_id' => $pass->id,
                'delegate_name' => $pass->name,
                'delegate_phone' => $pass->phone,
                'valid_from' => $pass->valid_from->toIso8601String(),
                'valid_until' => $pass->valid_until->toIso8601String(),
            ], $guardian, $child->tenant_id);

            return [$pass, $code];
        });

        $this->sms->send($phone, __('pickup.pass_sms', [
            'nursery' => $tenant->name,
            'child' => $child->first_name,
            'code' => $code,
            'until' => $pass->valid_until->format('d/m H:i'),
        ]));

        return ['pass' => $pass, 'code' => $code];
    }

    public function revoke(PickupPass $pass, User $by): PickupPass
    {
        $pass->update(['revoked_at' => now()]);

        $this->audit->record(AuditAction::PickupPassRevoked, $pass, ['pickup_pass_id' => $pass->id], $by, $pass->tenant_id);

        return $pass;
    }

    /**
     * @return Collection<int, PickupPass>
     */
    public function forChild(Child $child): Collection
    {
        return $child->pickupPasses()->latest('id')->limit(30)->get();
    }

    public function findUsable(Tenant $tenant, string $code): ?PickupPass
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return PickupPass::where('tenant_id', $tenant->id)
            ->where('code_hash', $this->hash($tenant, $code))
            ->usableNow()
            ->first();
    }

    private function uniqueCode(Tenant $tenant): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while ($this->findUsable($tenant, $code) !== null);

        return $code;
    }

    private function hash(Tenant $tenant, string $code): string
    {
        return hash_hmac('sha256', $tenant->id.'|'.$code, 'pickup-pass|'.config('app.key'));
    }
}
