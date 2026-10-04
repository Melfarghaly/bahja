<?php

namespace App\Services\Admin;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Reads and updates the platform branding (visual identity, logo, colors).
 */
class PlatformSettingsService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $logo = null): PlatformSetting
    {
        $settings = PlatformSetting::current();

        if ($logo !== null) {
            // Replace any previous logo to avoid orphan files.
            if ($settings->logo_path !== null) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $logo->store('branding', 'public');
        }

        $settings->update($data);

        return $settings;
    }
}
