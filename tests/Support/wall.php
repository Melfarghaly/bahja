<?php

use App\Enums\ConsentScope;
use App\Models\Child;
use App\Models\MediaConsent;
use Illuminate\Http\UploadedFile;

/**
 * A real JPEG (w × h) carrying an EXIF block: Orientation and a camera
 * "Make" that must never survive our processing.
 */
function jpegWithExif(int $width, int $height, int $orientation = 6, string $make = 'SpyPhone'): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 80));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    $makeData = $make."\0";
    $ifd = pack('v', 2)
        .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)              // Orientation (SHORT)
        .pack('vvVV', 0x010F, 2, strlen($makeData), 8 + 2 + 24 + 4) // Make (ASCII) → data after the IFD
        .pack('V', 0);
    $tiff = 'II'.pack('v', 0x2A).pack('V', 8).$ifd.$makeData;
    $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

    return new UploadedFile($path, 'IMG_0001.jpg', 'image/jpeg', null, true);
}

function grantPhotoConsent(Child $child, bool $group = true): void
{
    MediaConsent::factory()->create(['tenant_id' => $child->tenant_id, 'child_id' => $child->id, 'scope' => ConsentScope::Wall]);
    if ($group) {
        MediaConsent::factory()->create(['tenant_id' => $child->tenant_id, 'child_id' => $child->id, 'scope' => ConsentScope::GroupPhotos]);
    }
}
