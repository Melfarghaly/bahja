<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Admin\PlatformMetricsService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private PlatformMetricsService $metrics) {}

    public function index(): View
    {
        $recentTenants = Tenant::query()
            ->withCount('children')
            ->latest()
            ->limit(8)
            ->get();

        $recentSubscriptions = Subscription::query()
            ->with(['tenant:id,name', 'plan:id,name,price_egp'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'kpis' => $this->metrics->kpis(),
            'tenantsByPlan' => $this->metrics->tenantsByPlan(),
            'recentTenants' => $recentTenants,
            'recentSubscriptions' => $recentSubscriptions,
        ]);
    }
}
