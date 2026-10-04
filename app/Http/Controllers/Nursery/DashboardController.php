<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\Classroom;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(): View
    {
        $tenant = $this->tenantContext->get();
        $subscription = $tenant->activeSubscription()->with('plan')->first();
        $plan = $subscription?->plan;
        $childrenCount = Child::where('status', 'active')->count();
        $presentToday = Attendance::whereDate('date', today())->whereNotNull('checked_in_at')->count();

        return view('nursery.dashboard', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'childrenCount' => $childrenCount,
            'teachersCount' => $tenant->teachers()->count(),
            'classroomsCount' => Classroom::count(),
            'presentToday' => $presentToday,
            'absentToday' => max(0, $childrenCount - $presentToday),
            'attendanceRate' => $childrenCount > 0 ? (int) round(($presentToday / $childrenCount) * 100) : 0,
            'planUsage' => ['children' => $childrenCount, 'max_children' => $plan?->max_children],
            'weeklyAttendance' => $this->weeklyAttendance(),
            'recentChildren' => Child::with('classroom:id,name')->latest()->limit(5)->get(),
        ]);
    }

    /**
     * Present-count for each of the last 7 days (oldest first).
     *
     * @return array<int, array{label: string, count: int, is_today: bool}>
     */
    private function weeklyAttendance(): array
    {
        $counts = Attendance::query()
            ->whereNotNull('checked_in_at')
            ->whereBetween('date', [today()->subDays(6), today()])
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $key = $day->toDateString();
            $days[] = [
                'label' => $day->translatedFormat('D'),
                'count' => (int) ($counts[$key] ?? 0),
                'is_today' => $i === 0,
            ];
        }

        return $days;
    }
}
