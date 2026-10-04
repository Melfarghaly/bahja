<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RolloutFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolloutRequest;
use App\Models\Tenant;
use App\Services\RolloutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RolloutController extends Controller
{
    public function __construct(private RolloutService $rollouts) {}

    public function index(): View
    {
        return view('admin.rollouts.index', [
            'flags' => collect(RolloutFlag::cases())->map(fn (RolloutFlag $flag) => [
                'flag' => $flag,
                'everyone' => $this->rollouts->releasedToEveryone($flag),
            ]),
        ]);
    }

    public function updateEveryone(UpdateRolloutRequest $request, RolloutFlag $flag): RedirectResponse
    {
        $active = $request->boolean('active');
        $this->rollouts->setForEveryone($flag, $active, $request->user());

        return back()->with('status', $active ? "تم إطلاق «{$flag->label()}» لكل الحضانات." : "تم إيقاف «{$flag->label()}» لكل الحضانات.");
    }

    public function updateTenant(UpdateRolloutRequest $request, Tenant $tenant, RolloutFlag $flag): RedirectResponse
    {
        $request->boolean('active')
            ? $this->rollouts->enable($tenant, $flag, $request->user())
            : $this->rollouts->disable($tenant, $flag, $request->user());

        return back()->with('status', "تم تحديث «{$flag->label()}» لحضانة «{$tenant->name}».");
    }
}
