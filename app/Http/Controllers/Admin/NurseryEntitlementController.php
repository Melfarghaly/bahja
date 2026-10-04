<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntitlementOverrideRequest;
use App\Http\Requests\Admin\StoreTenantAddonRequest;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantEntitlementOverride;
use App\Services\Admin\TenantEntitlementAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NurseryEntitlementController extends Controller
{
    public function __construct(private TenantEntitlementAdminService $service) {}

    public function storeOverride(StoreEntitlementOverrideRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->grantOverride($tenant, $request->validated(), $request->user());

        return back()->with('status', 'تم حفظ الاستثناء.');
    }

    public function destroyOverride(Request $request, Tenant $tenant, TenantEntitlementOverride $entitlementOverride): RedirectResponse
    {
        $this->service->revokeOverride($tenant, $entitlementOverride, $request->user());

        return back()->with('status', 'تم إلغاء الاستثناء.');
    }

    public function storeAddon(StoreTenantAddonRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->service->addAddon($tenant, $request->validated(), $request->user());

        return back()->with('status', 'تمت إضافة الإضافة.');
    }

    public function destroyAddon(Request $request, Tenant $tenant, TenantAddon $addon): RedirectResponse
    {
        $this->service->removeAddon($tenant, $addon, $request->user());

        return back()->with('status', 'تم حذف الإضافة.');
    }
}
