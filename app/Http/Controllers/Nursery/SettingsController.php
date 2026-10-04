<?php

namespace App\Http\Controllers\Nursery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\UpdateNurseryRequest;
use App\Services\NurserySettingsService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function __construct(
        private NurserySettingsService $settings,
        private TenantContext $tenantContext,
    ) {}

    public function edit(): View
    {
        return view('nursery.settings.edit', ['tenant' => $this->tenantContext->get()]);
    }

    public function update(UpdateNurseryRequest $request): RedirectResponse
    {
        $this->settings->update($this->tenantContext->get(), $request->validated());

        return back()->with('status', 'تم حفظ إعدادات الحضانة.');
    }
}
