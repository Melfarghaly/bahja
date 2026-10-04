<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\MemberType;
use App\Enums\RolloutFlag;
use App\Enums\TeacherStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Who the signed-in user is and what they can do in each nursery — the first
 * call an app makes, to pick the nursery (X-Tenant-Id) and the screens to show.
 */
class ProfileService
{
    public function __construct(
        private TenantContext $tenantContext,
        private EntitlementService $entitlements,
        private RolloutService $rollouts,
        private CheckoutGatewayRegistry $gateways,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nurseries(User $user): array
    {
        $teaching = $user->nurseriesAsTeacher()
            ->wherePivot('status', '!=', TeacherStatus::Inactive->value)
            ->pluck('tenants.id')
            ->all();

        $previous = $this->tenantContext->get();

        try {
            return $user->tenants()
                ->wherePivot('status', 'active')
                ->orderBy('tenants.name')
                ->get()
                ->map(fn (Tenant $tenant) => $this->describe($user, $tenant, in_array($tenant->id, $teaching, true)))
                ->values()
                ->all();
        } finally {
            $previous ? $this->tenantContext->set($previous) : $this->tenantContext->forget();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(User $user, Tenant $tenant, bool $teaches): array
    {
        // Guardian links live under Row-Level Security: read them in the nursery's context.
        $this->tenantContext->set($tenant);

        $isGuardian = DB::table('child_guardian')
            ->where('tenant_id', $tenant->id)
            ->where('guardian_id', $user->id)
            ->where('custody_flag', '!=', 'blocked')
            ->exists();

        $memberType = MemberType::from($tenant->pivot->member_type);
        $manages = in_array($memberType, [MemberType::Owner, MemberType::Admin], true);

        $roles = array_values(array_unique(array_filter([
            $memberType->value,
            $teaches ? MemberType::Teacher->value : null,
            $isGuardian ? MemberType::Guardian->value : null,
        ])));

        $bahgaPay = $this->rollouts->active($tenant, RolloutFlag::BahgaPay);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null,
            'roles' => $roles,
            'capabilities' => [
                'manage_nursery' => $manages,
                'take_attendance' => $manages || $teaches,
                'view_children' => $manages || $teaches,
                'manage_children' => $manages,
                'guardian' => $isGuardian,
                'bahga_pay' => $bahgaPay,
                'online_payments' => $bahgaPay
                    && $this->entitlements->for($tenant)->allows(Feature::AutoCollection)
                    && $this->gateways->available($tenant) !== [],
            ],
        ];
    }
}
