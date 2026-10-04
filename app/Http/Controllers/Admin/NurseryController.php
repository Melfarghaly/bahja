<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Limit;
use App\Enums\RolloutFlag;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Admin\NurseryAdminService;
use App\Services\EntitlementService;
use App\Services\RolloutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NurseryController extends Controller
{
    public function __construct(
        private NurseryAdminService $nurseries,
        private EntitlementService $entitlements,
        private RolloutService $rollouts,
    ) {}

    public function index(Request $request): View
    {
        $tenants = Tenant::query()
            ->withCount('children')
            ->with(['activeSubscription' => fn ($q) => $q->with('plan:id,name')])
            ->when($request->string('q')->toString(), function ($query, string $term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.nurseries.index', [
            'tenants' => $tenants,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Tenant $tenant): View
    {
        $tenant->loadCount(['children', 'classrooms'])
            ->load([
                'members:id,name,phone,email',
                'activeSubscription.plan',
                'entitlementOverrides.grantedBy:id,name',
                'addons',
            ]);

        return view('admin.nurseries.show', [
            'tenant' => $tenant,
            'entitlements' => $this->entitlements->for($tenant),
            'rollouts' => collect(RolloutFlag::cases())
                ->mapWithKeys(fn (RolloutFlag $flag) => [$flag->value => $this->rollouts->active($tenant, $flag)])
                ->all(),
            'usage' => [
                Limit::Children->value => $this->entitlements->usage($tenant, Limit::Children),
                Limit::Staff->value => $this->entitlements->usage($tenant, Limit::Staff),
            ],
        ]);
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $this->nurseries->suspend($tenant);

        return back()->with('status', "تم تعليق حضانة «{$tenant->name}».");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $this->nurseries->activate($tenant);

        return back()->with('status', "تم تفعيل حضانة «{$tenant->name}».");
    }
}
