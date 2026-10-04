<?php

namespace App\Http\Controllers\Nursery;

use App\Enums\AttendanceMethod;
use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function index(): View
    {
        $children = Child::query()
            ->where('status', 'active')
            ->with([
                'classroom:id,name',
                'attendances' => fn ($q) => $q->whereDate('date', today()),
                // Only pickup-authorized guardians populate the check-out dropdown.
                'guardians' => fn ($q) => $q->wherePivot('can_pickup', true),
            ])
            ->orderBy('first_name')
            ->get();

        return view('nursery.attendance.index', ['children' => $children]);
    }

    public function checkIn(Request $request, Child $child): RedirectResponse
    {
        $this->attendance->checkIn($child, $request->user(), AttendanceMethod::Manual);

        return back()->with('status', "تم تسجيل حضور {$child->first_name}.");
    }

    public function checkOut(Request $request, Child $child): RedirectResponse
    {
        $data = $request->validate([
            'collector_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $collector = User::findOrFail($data['collector_id']);
            $this->attendance->checkOut($child, $collector);
        } catch (AuthorizationException $e) {
            return back()->with('error', 'هذا الشخص غير مخوَّل باستلام هذا الطفل.');
        }

        return back()->with('status', "تم تسجيل انصراف {$child->first_name} والتحقق من المستلِم.");
    }
}
