<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Services\Admin\PlatformSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function __construct(private PlatformSettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => PlatformSetting::current()]);
    }

    public function update(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        $this->settings->update(
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return back()->with('status', 'تم حفظ إعدادات الهوية البصرية.');
    }
}
