<?php

namespace App\Http\Controllers\Nursery\Finance;

use App\Enums\TuitionPaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\Tuition\CollectionsReportService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CollectionsDashboardController extends Controller
{
    public function __construct(
        private CollectionsReportService $reports,
        private TenantContext $tenantContext,
    ) {}

    public function __invoke(Request $request): View
    {
        $tenant = $this->tenantContext->get();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $request->string('month')->toString())
            ? CarbonImmutable::createFromFormat('!Y-m', $request->string('month')->toString())
            : CarbonImmutable::now()->startOfMonth();

        $aging = $this->reports->aging($tenant);

        return view('nursery.finance.dashboard', [
            'month' => $month,
            'summary' => $this->reports->summary($tenant, $month),
            'aging' => $aging,
            'agingMax' => max(1, ...array_map(fn ($b) => $b['amount']->piasters, $aging)),
            'lateFamilies' => $this->reports->topLateFamilies($tenant),
            'byMethod' => $this->reports->collectedByMethod($tenant, $month),
            'methods' => TuitionPaymentMethod::cases(),
        ]);
    }
}
